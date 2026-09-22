<?php

use App\InventoryMovementType;
use App\Livewire\PurchaseOrderForm;
use App\Livewire\PurchaseOrderTable;
use App\Livewire\PurchaseReceiving;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Permission;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\PurchaseOrderStatus as Status;
use App\Services\PurchaseOrderService;
use App\Services\ReceivingService;
use Database\Seeders\PurchasingPermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(PurchasingPermissionSeeder::class);
    $this->actor = User::factory()->create(['is_active' => true]);
    $this->actor->roles()->attach(Role::where('slug', 'purchasing-manager')->firstOrFail());
    $this->supplier = Supplier::factory()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->stockItem = StockItem::factory()->create(['sku' => 'PURCHASE-SKU', 'barcode' => '987654321']);
    $this->orders = app(PurchaseOrderService::class);
    $this->receiving = app(ReceivingService::class);
    $this->data = [
        'supplier_id' => $this->supplier->id, 'warehouse_id' => $this->warehouse->id,
        'ordered_at' => '2026-09-19', 'expected_at' => '2026-09-20', 'notes' => 'Purchase test',
        'items' => [['stock_item_id' => $this->stockItem->id, 'ordered_quantity' => '2.5', 'unit_cost' => '10.1234', 'discount' => '1.0000', 'tax' => '2.0000']],
    ];
});

test('creating editing and ordering purchases never changes existing stock or the ledger', function () {
    $inventory = Inventory::factory()->create(['stock_item_id' => $this->stockItem->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => '7.0000', 'reserved_quantity' => '1.0000']);
    $order = $this->orders->saveDraft([...$this->data, 'status' => 'received', 'created_by' => 999, 'total' => '1'], $this->actor);
    expect($order->status)->toBe(Status::Draft)->and($order->created_by)->toBe($this->actor->id)
        ->and($order->subtotal)->toBe('25.3085')->and($order->discount)->toBe('1.0000')->and($order->tax)->toBe('2.0000')->and($order->total)->toBe('26.3085')
        ->and($order->items->sole()->remainingQuantity())->toBe('2.5000');
    $order = $this->orders->saveDraft([...$this->data, 'notes' => 'Edited', 'revision' => $order->revision], $this->actor, $order);
    $order = $this->orders->transition($order, Status::Ordered, $this->actor);
    expect($order->status)->toBe(Status::Ordered)->and($order->approved_by)->toBe($this->actor->id)
        ->and($inventory->fresh()->quantity)->toBe('7.0000')->and($inventory->fresh()->reserved_quantity)->toBe('1.0000');
    $this->assertDatabaseCount('inventories', 1);
    $this->assertDatabaseCount('inventory_movements', 0);
    $this->assertDatabaseCount('purchase_receipts', 0);
    $this->assertDatabaseCount('purchase_order_items', 1);
});

test('partial then full receiving changes only the chosen warehouse with traceable purchase movements', function () {
    $other = Warehouse::factory()->create();
    Inventory::factory()->create(['stock_item_id' => $this->stockItem->id, 'warehouse_id' => $other->id, 'quantity' => '9']);
    $order = $this->orders->saveDraft($this->data, $this->actor);
    $this->orders->transition($order, Status::Ordered, $this->actor);
    $item = $order->items()->sole();
    foreach (['1.0001', '1.4999'] as $index => $quantity) {
        $receipt = $this->receiving->receive($order, [
            'idempotency_key' => (string) Str::uuid(), 'notes' => 'Delivery',
            'items' => [['purchase_order_item_id' => $item->id, 'quantity' => $quantity]],
        ], $this->actor);
        expect($order->fresh()->status)->toBe($index === 0 ? Status::PartiallyReceived : Status::Received);
        $movement = InventoryMovement::latest('id')->firstOrFail();
        expect($movement->type)->toBe(InventoryMovementType::Purchase)->and($movement->quantity_delta)->toBe($quantity)
            ->and($movement->reference_type)->toBe(PurchaseReceipt::class)->and($movement->reference_id)->toBe($receipt->id)
            ->and($movement->reference->id)->toBe($receipt->id)->and($movement->performed_by)->toBe($this->actor->id)
            ->and($movement->unit_cost)->toBe('10.1234')->and($receipt->items->sole()->purchase_order_item_id)->toBe($item->id);
    }
    expect(Inventory::where('warehouse_id', $this->warehouse->id)->sole()->quantity)->toBe('2.5000');
    expect(Inventory::where('warehouse_id', $other->id)->sole()->quantity)->toBe('9.0000');
    expect($item->fresh()->remainingQuantity())->toBe('0.0000');
    $this->assertDatabaseCount('purchase_receipts', 2);
    $this->assertDatabaseCount('inventory_movements', 2);
});

test('receipt retries are idempotent and changed submissions cannot reuse their key', function () {
    $order = $this->orders->saveDraft($this->data, $this->actor);
    $this->orders->transition($order, Status::Ordered, $this->actor);
    $data = ['idempotency_key' => (string) Str::uuid(), 'items' => [['purchase_order_item_id' => $order->items()->sole()->id, 'quantity' => '2.5']]];
    $first = $this->receiving->receive($order, $data, $this->actor);
    expect($this->receiving->receive($order, $data, $this->actor)->id)->toBe($first->id);
    $data['items'][0]['quantity'] = '1';
    expect(fn () => $this->receiving->receive($order, $data, $this->actor))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('purchase_receipts', 1);
    $this->assertDatabaseCount('inventory_movements', 1);
    expect(Inventory::sole()->quantity)->toBe('2.5000');
});

test('receiving rejects excessive zero negative malformed and overprecision quantities', function (string $quantity) {
    $order = $this->orders->saveDraft($this->data, $this->actor);
    $this->orders->transition($order, Status::Ordered, $this->actor);
    expect(fn () => $this->receiving->receive($order, ['idempotency_key' => (string) Str::uuid(), 'items' => [
        ['purchase_order_item_id' => $order->items()->sole()->id, 'quantity' => $quantity],
    ]], $this->actor))->toThrow(ValidationException::class);
    expect($order->fresh()->status)->toBe(Status::Ordered)->and($order->items()->sole()->received_quantity)->toBe('0.0000');
    $this->assertDatabaseCount('purchase_receipts', 0);
    $this->assertDatabaseCount('inventories', 0);
})->with(['2.5001', '0', '-1', '1.12345', 'abc', '1e2', '10000000000000000']);

test('receiving rejects unrelated duplicate and missing lines', function () {
    $order = $this->orders->saveDraft($this->data, $this->actor);
    $this->orders->transition($order, Status::Ordered, $this->actor);
    $unrelated = PurchaseOrderItem::factory()->create();
    $line = ['purchase_order_item_id' => $order->items()->sole()->id, 'quantity' => '1'];
    foreach ([[], [$line, $line], [['purchase_order_item_id' => $unrelated->id, 'quantity' => '1']]] as $lines) {
        expect(fn () => $this->receiving->receive($order, ['idempotency_key' => (string) Str::uuid(), 'items' => $lines], $this->actor))->toThrow(ValidationException::class);
    }
    $this->assertDatabaseCount('inventory_movements', 0);
    $this->assertDatabaseCount('purchase_receipts', 0);
});

test('receipt failure on a later movement rolls back every line balance receipt and order update', function () {
    $second = StockItem::factory()->create();
    $this->data['items'][] = [...$this->data['items'][0], 'stock_item_id' => $second->id];
    $order = $this->orders->saveDraft($this->data, $this->actor);
    $this->orders->transition($order, Status::Ordered, $this->actor);
    $calls = 0;
    Event::listen('eloquent.creating: '.InventoryMovement::class, function () use (&$calls): void {
        if (++$calls === 2) {
            throw new RuntimeException('Receipt ledger failure');
        }
    });
    try {
        expect(fn () => $this->receiving->receive($order, [
            'idempotency_key' => (string) Str::uuid(),
            'items' => $order->items->map(fn (PurchaseOrderItem $item): array => ['purchase_order_item_id' => $item->id, 'quantity' => '1'])->all(),
        ], $this->actor))->toThrow(RuntimeException::class, 'Receipt ledger failure');
    } finally {
        Event::forget('eloquent.creating: '.InventoryMovement::class);
    }
    foreach (['purchase_receipts', 'purchase_receipt_items', 'inventories', 'inventory_movements'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
    expect($order->fresh()->status)->toBe(Status::Ordered);
    expect($order->items()->where('received_quantity', '!=', 0)->count())->toBe(0);
});

test('drafts cancelled and fully received orders reject new receipts', function (Status $status) {
    $order = PurchaseOrder::factory()->create(['status' => $status]);
    $line = PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id]);
    expect(fn () => $this->receiving->receive($order, ['idempotency_key' => (string) Str::uuid(), 'items' => [
        ['purchase_order_item_id' => $line->id, 'quantity' => '1'],
    ]], $this->actor))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('inventories', 0);
})->with([Status::Draft, Status::Cancelled, Status::Received]);

test('ordered purchases cannot be edited or manually marked received and cancellation never changes stock', function () {
    $order = $this->orders->saveDraft($this->data, $this->actor);
    $this->orders->transition($order, Status::Ordered, $this->actor);
    expect(fn () => $this->orders->saveDraft([...$this->data, 'revision' => 2], $this->actor, $order))->toThrow(ValidationException::class);
    foreach ([Status::Draft, Status::Ordered, Status::PartiallyReceived, Status::Received] as $status) {
        expect(fn () => $this->orders->transition($order, $status, $this->actor))->toThrow(ValidationException::class);
    }
    $this->orders->transition($order, Status::Cancelled, $this->actor);
    expect($order->fresh()->status)->toBe(Status::Cancelled);
    expect(fn () => $this->orders->transition($order, Status::Ordered, $this->actor))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('inventories', 0);
    $this->assertDatabaseCount('inventory_movements', 0);
});

test('partially received orders cannot be cancelled', function () {
    $order = $this->orders->saveDraft($this->data, $this->actor);
    $this->orders->transition($order, Status::Ordered, $this->actor);
    $this->receiving->receive($order, ['idempotency_key' => (string) Str::uuid(), 'items' => [
        ['purchase_order_item_id' => $order->items()->sole()->id, 'quantity' => '1'],
    ]], $this->actor);
    expect(fn () => $this->orders->transition($order, Status::Cancelled, $this->actor))->toThrow(ValidationException::class);
    expect($order->fresh()->status)->toBe(Status::PartiallyReceived)->and(Inventory::sole()->quantity)->toBe('1.0000');
});

test('draft validation rejects invalid fields and computed amount overflow', function (string $field, mixed $value) {
    Arr::set($this->data, $field, $value);
    expect(fn () => $this->orders->saveDraft($this->data, $this->actor))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('purchase_orders', 0);
    $this->assertDatabaseCount('inventories', 0);
})->with([
    ['supplier_id', 999999], ['warehouse_id', 999999], ['ordered_at', 'not-date'], ['expected_at', '2026-09-18'],
    ['items', []], ['items.0.stock_item_id', 999999], ['items.0.ordered_quantity', '0'],
    ['items.0.ordered_quantity', '-1'], ['items.0.unit_cost', '-1'], ['items.0.unit_cost', '0.12345'],
    ['items.0.unit_cost', '9999999999999999.9999'], ['items.0.discount', '30'], ['items.0.tax', '-1'],
    ['items.0.received_quantity', '10'],
]);

test('duplicate stock items and stale draft revisions are rejected without losing edits', function () {
    $this->data['items'][] = $this->data['items'][0];
    expect(fn () => $this->orders->saveDraft($this->data, $this->actor))->toThrow(ValidationException::class);
    array_pop($this->data['items']);
    $order = $this->orders->saveDraft($this->data, $this->actor);
    $this->orders->saveDraft([...$this->data, 'notes' => 'New edit', 'revision' => 1], $this->actor, $order);
    expect(fn () => $this->orders->saveDraft([...$this->data, 'revision' => 1], $this->actor, $order))->toThrow(ValidationException::class);
    expect($order->fresh()->notes)->toBe('New edit');
});

test('inactive parties products variants and invalid stock identities cannot be purchased', function (string $target) {
    match ($target) {
        'supplier' => $this->supplier->update(['status' => 'inactive']),
        'warehouse' => $this->warehouse->update(['status' => 'inactive']),
        'item' => $this->stockItem->update(['is_active' => false]),
        'product' => $this->stockItem->product->update(['status' => 'inactive']),
        'identity' => $this->stockItem->product->forceFill(['has_variants' => true])->save(),
    };
    expect(fn () => $this->orders->saveDraft($this->data, $this->actor))->toThrow(ValidationException::class);
})->with(['supplier', 'warehouse', 'item', 'product', 'identity']);

test('ordering and receiving revalidate stock that was deactivated after drafting', function () {
    $order = $this->orders->saveDraft($this->data, $this->actor);
    $this->stockItem->update(['is_active' => false]);
    expect(fn () => $this->orders->transition($order, Status::Ordered, $this->actor))->toThrow(ValidationException::class);
    $this->stockItem->update(['is_active' => true]);
    $this->orders->transition($order, Status::Ordered, $this->actor);
    $this->stockItem->update(['is_active' => false]);
    expect(fn () => $this->receiving->receive($order, ['idempotency_key' => (string) Str::uuid(), 'items' => [
        ['purchase_order_item_id' => $order->items()->sole()->id, 'quantity' => '1'],
    ]], $this->actor))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('purchase_receipts', 0);
    $this->assertDatabaseCount('inventory_movements', 0);
});

test('variants use the same purchasing and receiving stock identity', function () {
    $variant = ProductVariant::factory()->create();
    $stock = StockItem::factory()->create(['product_id' => null, 'product_variant_id' => $variant->id]);
    $this->data['items'][0]['stock_item_id'] = $stock->id;
    $order = $this->orders->saveDraft($this->data, $this->actor);
    $this->orders->transition($order, Status::Ordered, $this->actor);
    $this->receiving->receive($order, ['idempotency_key' => (string) Str::uuid(), 'items' => [
        ['purchase_order_item_id' => $order->items()->sole()->id, 'quantity' => '2.5'],
    ]], $this->actor);
    expect(Inventory::sole()->stock_item_id)->toBe($stock->id)->and(Inventory::sole()->quantity)->toBe('2.5000');
});

test('livewire supports product search line edits exact totals removal and saving without stock changes', function () {
    $component = Livewire::actingAs($this->actor)->test(PurchaseOrderForm::class)
        ->set('search', '987654321')->assertSee('PURCHASE-SKU')->call('addItem', $this->stockItem->id)
        ->assertSet('form.items.0.stock_item_id', $this->stockItem->id)
        ->call('addItem', $this->stockItem->id)->assertHasErrors('form.items')
        ->set('form.items.0.ordered_quantity', '2.5')->set('form.items.0.unit_cost', '10.1234')
        ->set('form.items.0.discount', '1')->set('form.items.0.tax', '2')
        ->assertViewHas('totals', fn (array $totals): bool => $totals['total'] === '26.3085')
        ->call('removeItem', 0)->assertSet('form.items', [])
        ->set('form', $this->data)->call('save')->assertHasNoErrors();
    $order = PurchaseOrder::sole();
    $component->assertRedirect(route('purchase-orders.show', $order));
    $this->assertDatabaseCount('inventories', 0);
    $this->assertDatabaseCount('inventory_movements', 0);
    Livewire::test(PurchaseOrderForm::class, ['order' => $order])->set('form.notes', 'Updated from Livewire')->call('save')->assertHasNoErrors();
    expect($order->fresh()->notes)->toBe('Updated from Livewire');
});

test('livewire validates fields and rejects revoked permissions', function () {
    $component = Livewire::actingAs($this->actor)->test(PurchaseOrderForm::class)->call('save')
        ->assertHasErrors(['form.supplier_id', 'form.warehouse_id', 'form.items']);
    $this->actor->roles()->detach();
    $component->call('addItem', $this->stockItem->id)->assertForbidden();
});

test('livewire receiving supports partial deliveries and displays overreceipt validation', function () {
    $order = $this->orders->saveDraft($this->data, $this->actor);
    $this->orders->transition($order, Status::Ordered, $this->actor);
    $id = $order->items()->sole()->id;
    $component = Livewire::actingAs($this->actor)->test(PurchaseReceiving::class, ['order' => $order])
        ->set("quantities.$id", '3')->call('receive')->assertHasErrors('items')
        ->set("quantities.$id", '1')->call('receive');
    expect($order->fresh()->status)->toBe(Status::PartiallyReceived);
    $component->assertRedirect(route('purchase-orders.receipt', [$order, PurchaseReceipt::sole()]));
});

test('purchase pages routes filters pagination and receipt ownership are protected', function () {
    $order = $this->orders->saveDraft($this->data, $this->actor);
    $this->get(route('purchase-orders.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['is_active' => true]));
    foreach (['index', 'create', 'show', 'edit', 'receive'] as $route) {
        $this->get(route('purchase-orders.'.$route, $order))->assertForbidden();
    }
    $this->patch(route('purchase-orders.status', $order), ['status' => 'ordered'])->assertForbidden();
    Livewire::test(PurchaseOrderTable::class)->assertForbidden();
    Livewire::test(PurchaseOrderForm::class)->assertForbidden();
    Livewire::test(PurchaseReceiving::class, ['order' => $order])->assertForbidden();
    $this->actingAs($this->actor);
    $this->get(route('purchase-orders.index'))->assertOk()->assertSeeLivewire(PurchaseOrderTable::class);
    $this->get(route('purchase-orders.create'))->assertOk()->assertSeeLivewire(PurchaseOrderForm::class);
    $this->get(route('purchase-orders.edit', $order))->assertOk();
    $this->get(route('purchase-orders.show', $order))->assertOk()->assertSee('Mark ordered');
    $this->patch(route('purchase-orders.status', $order), ['status' => 'received'])->assertSessionHasErrors('status');
    $this->patch(route('purchase-orders.status', $order), ['status' => 'ordered'])->assertSessionHasNoErrors();
    $this->get(route('purchase-orders.receive', $order))->assertOk();
    $receipt = $this->receiving->receive($order, ['idempotency_key' => (string) Str::uuid(), 'items' => [['purchase_order_item_id' => $order->items()->sole()->id, 'quantity' => '1']]], $this->actor);
    $this->get(route('purchase-orders.receipt', [$order, $receipt]))->assertOk()->assertSee('PURCHASE-SKU');
    $other = PurchaseOrder::factory()->create();
    $this->get(route('purchase-orders.receipt', [$other, $receipt]))->assertNotFound();
    PurchaseOrder::factory()->count(11)->create();
    Livewire::test(PurchaseOrderTable::class)->assertViewHas('orders', fn ($rows): bool => $rows->total() === 13)
        ->call('setPage', 2)->set('search', $order->number)
        ->assertViewHas('orders', fn ($rows): bool => $rows->currentPage() === 1 && $rows->total() === 1)
        ->set('status', 'draft')->assertSee('No purchase orders match your filters.')
        ->set('status', 'partially_received')->assertSee($order->number)
        ->set('warehouse', (string) $other->warehouse_id)->assertSee('No purchase orders match your filters.')
        ->call('clearFilters')->set('supplier', (string) $this->supplier->id)
        ->assertViewHas('orders', fn ($rows): bool => $rows->total() === 1);
});

test('view-only users cannot mutate through services and inactive managers are denied', function () {
    $order = $this->orders->saveDraft($this->data, $this->actor);
    $role = Role::create(['name' => 'Purchase Viewer', 'slug' => 'purchase-viewer']);
    $role->permissions()->attach(Permission::where('slug', 'purchasing.view')->sole());
    $this->actor->roles()->sync([$role->id]);
    $this->actingAs($this->actor)->get(route('purchase-orders.show', $order))->assertOk()->assertDontSee('Edit draft')->assertDontSee('Mark ordered');
    expect(fn () => $this->orders->saveDraft($this->data, $this->actor))->toThrow(AuthorizationException::class);
    expect(fn () => $this->orders->transition($order, Status::Ordered, $this->actor))->toThrow(AuthorizationException::class);
    expect(fn () => $this->receiving->receive($order, [], $this->actor))->toThrow(AuthorizationException::class);
    $this->actor->update(['is_active' => false]);
    $this->get(route('purchase-orders.index'))->assertForbidden();
});

test('receipts are immutable and purchase balances reject mass assignment', function () {
    $order = $this->orders->saveDraft($this->data, $this->actor);
    $this->orders->transition($order, Status::Ordered, $this->actor);
    $receipt = $this->receiving->receive($order, ['idempotency_key' => (string) Str::uuid(), 'items' => [['purchase_order_item_id' => $order->items()->sole()->id, 'quantity' => '1']]], $this->actor);
    foreach ([$receipt, $receipt->items->sole()] as $model) {
        expect(fn () => $model->forceFill(['unit_cost' => 999])->save())->toThrow(LogicException::class);
        expect(fn () => $model->delete())->toThrow(LogicException::class);
    }
    expect($order->getGuarded())->toBe(['*'])->and($order->items()->sole()->getGuarded())->toBe(['*']);
});

test('purchasing permission grants are idempotent and cannot create users', function () {
    $this->seed(PurchasingPermissionSeeder::class);
    expect(Permission::count())->toBe(6);
    $this->artisan('purchasing:grant', ['email' => $this->actor->email])->assertSuccessful();
    $this->artisan('purchasing:grant', ['email' => $this->actor->email])->assertSuccessful();
    expect($this->actor->roles()->count())->toBe(1);
    $this->artisan('purchasing:grant', ['email' => 'missing@example.test'])->assertFailed();
    expect(User::count())->toBe(1);
});

test('failed draft item replacement restores the original order and its items', function () {
    $order = $this->orders->saveDraft($this->data, $this->actor);
    $itemId = $order->items()->sole()->id;
    Event::listen('eloquent.creating: '.PurchaseOrderItem::class, function (): void {
        throw new RuntimeException('Line persistence failure');
    });
    try {
        expect(fn () => $this->orders->saveDraft([...$this->data, 'notes' => 'Failed edit', 'revision' => 1], $this->actor, $order))
            ->toThrow(RuntimeException::class, 'Line persistence failure');
    } finally {
        Event::forget('eloquent.creating: '.PurchaseOrderItem::class);
    }
    expect($order->fresh()->notes)->toBe('Purchase test')->and($order->fresh()->revision)->toBe(1)
        ->and($order->items()->sole()->id)->toBe($itemId);
});

test('unavailable order screens return conflict instead of partially mounting forms', function () {
    $draft = $this->orders->saveDraft($this->data, $this->actor);
    $this->actingAs($this->actor)->get(route('purchase-orders.receive', $draft))->assertStatus(409);
    Livewire::test(PurchaseReceiving::class, ['order' => $draft])->assertStatus(409);
    $this->orders->transition($draft, Status::Ordered, $this->actor);
    $this->get(route('purchase-orders.edit', $draft))->assertStatus(409);
    Livewire::test(PurchaseOrderForm::class, ['order' => $draft])->assertStatus(409);
});

test('line amounts round half up at four decimals and total the persisted lines', function () {
    $this->data['items'][0] = [...$this->data['items'][0], 'ordered_quantity' => '0.5', 'unit_cost' => '0.0001', 'discount' => '0', 'tax' => '0'];
    $order = $this->orders->saveDraft($this->data, $this->actor);
    expect($order->total)->toBe('0.0001')->and($order->items()->sole()->subtotal)->toBe('0.0001');
});

test('numeric payloads are normalized without silently truncating fractional amounts', function () {
    $this->data['items'][0] = [...$this->data['items'][0], 'ordered_quantity' => 2.5, 'unit_cost' => 10.1234, 'discount' => 0.5, 'tax' => 0.25];
    $order = $this->orders->saveDraft($this->data, $this->actor);
    expect($order->subtotal)->toBe('25.3085')->and($order->total)->toBe('25.0585');
    $this->orders->transition($order, Status::Ordered, $this->actor);
    $this->receiving->receive($order, ['idempotency_key' => (string) Str::uuid(), 'items' => [
        ['purchase_order_item_id' => $order->items()->sole()->id, 'quantity' => 1.5],
    ]], $this->actor);
    expect(Inventory::sole()->quantity)->toBe('1.5000');
});

test('purchasing migration preserves legacy orders and backfills line and header totals', function () {
    $migration = require database_path('migrations/2026_09_19_051209_extend_purchasing_tables.php');
    $migration->down();
    $id = DB::table('purchase_orders')->insertGetId([
        'number' => 'LEGACY-PO', 'supplier_id' => $this->supplier->id, 'warehouse_id' => $this->warehouse->id,
        'created_by' => $this->actor->id, 'status' => 'ordered', 'ordered_at' => '2026-09-18',
    ]);
    DB::table('purchase_order_items')->insert([
        'purchase_order_id' => $id, 'stock_item_id' => $this->stockItem->id, 'ordered_quantity' => '2.5', 'unit_cost' => '10.1234', 'received_quantity' => '1',
    ]);
    $migration->up();
    $order = PurchaseOrder::findOrFail($id);
    expect($order->total)->toBe('25.3085')->and($order->status)->toBe(Status::Ordered)
        ->and($order->items()->sole()->subtotal)->toBe('25.3085')
        ->and($order->items()->sole()->received_quantity)->toBe('1.0000');
});
