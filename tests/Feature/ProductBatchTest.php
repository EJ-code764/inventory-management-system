<?php

use App\InventoryMovementType as Movement;
use App\Livewire\ProductBatchTable;
use App\Livewire\PurchaseReceiving;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\ProductBatch;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\PurchaseOrderStatus;
use App\Services\InventoryService;
use App\Services\PurchaseOrderService;
use App\Services\ReceivingService;
use Database\Seeders\ProductBatchPermissionSeeder;
use Database\Seeders\PurchasingPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed([PurchasingPermissionSeeder::class, ProductBatchPermissionSeeder::class]);
    $this->actor = User::factory()->create(['is_active' => true]);
    $this->actor->roles()->attach(Role::whereIn('slug', ['purchasing-manager', 'batch-viewer'])->pluck('id'));
    $this->stock = StockItem::factory()->create();
    $this->warehouse = Warehouse::factory()->create();
    $orders = app(PurchaseOrderService::class);
    $this->order = $orders->saveDraft([
        'supplier_id' => Supplier::factory()->create()->id, 'warehouse_id' => $this->warehouse->id, 'ordered_at' => today()->format('Y-m-d'),
        'items' => [['stock_item_id' => $this->stock->id, 'ordered_quantity' => '100', 'unit_cost' => '12.3456', 'discount' => '0', 'tax' => '0']],
    ], $this->actor);
    $this->order = $orders->transition($this->order, PurchaseOrderStatus::Ordered, $this->actor);
    $this->line = $this->order->items()->sole();
    $this->data = [
        'idempotency_key' => (string) Str::uuid(),
        'items' => [['purchase_order_item_id' => $this->line->id, 'quantity' => '10.1234', 'batch_number' => 'LOT-FIRST', 'expiration_date' => '2026-10-10']],
    ];
    $this->receive = fn (array $data) => app(ReceivingService::class)->receive($this->order, $data, $this->actor);
    $this->inventoryService = app(InventoryService::class);
});

test('receiving atomically creates batch receipt snapshots and purchase movements', function () {
    $receipt = ($this->receive)($this->data);
    $batch = ProductBatch::sole();
    expect($batch->quantity)->toBe('10.1234')->and($batch->unit_cost)->toBe('12.3456')
        ->and($batch->stock_item_id)->toBe($this->stock->id)->and($batch->warehouse_id)->toBe($this->warehouse->id)
        ->and($batch->expiration_date->format('Y-m-d'))->toBe('2026-10-10')
        ->and($batch->purchase_receipt_item_id)->toBe($receipt->items()->sole()->id)
        ->and(Inventory::sole()->quantity)->toBe('10.1234')
        ->and(InventoryMovement::sole()->product_batch_id)->toBe($batch->id)
        ->and($receipt->items()->sole()->batch_number)->toBe('LOT-FIRST');
    ($this->receive)($this->data);
    $this->assertDatabaseCount('product_batches', 1);
    $this->assertDatabaseCount('inventory_movements', 1);
    $data = [...$this->data, 'idempotency_key' => (string) Str::uuid()];
    ($this->receive)($data);
    expect($batch->fresh()->quantity)->toBe('20.2468');
    $this->assertDatabaseCount('product_batches', 1);
});

test('untracked receiving remains compatible and batch zero is a valid number', function () {
    ($this->receive)(['idempotency_key' => (string) Str::uuid(), 'items' => [['purchase_order_item_id' => $this->line->id, 'quantity' => '5']]]);
    $this->assertDatabaseCount('product_batches', 0);
    $data = $this->data;
    $data['items'][0]['batch_number'] = '0';
    $data['items'][0]['expiration_date'] = null;
    ($this->receive)($data);
    expect(ProductBatch::sole()->batch_number)->toBe('0')->and(ProductBatch::sole()->expiration_date)->toBeNull();
});

test('invalid optional batch metadata is rejected', function (string $field, mixed $value) {
    $data = $this->data;
    $data['items'][0][$field] = $value;
    expect(fn () => ($this->receive)($data))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('product_batches', 0);
    $this->assertDatabaseCount('inventories', 0);
})->with([['batch_number', ''], ['batch_number', '   '], ['batch_number', null], ['batch_number', str_repeat('X', 256)], ['expiration_date', '2026-02-30'], ['expiration_date', 'tomorrow']]);

test('conflicting lot expiration or cost cannot corrupt receiving', function () {
    ($this->receive)($this->data);
    $data = [...$this->data, 'idempotency_key' => (string) Str::uuid()];
    $data['items'][0]['expiration_date'] = '2027-01-01';
    expect(fn () => ($this->receive)($data))->toThrow(ValidationException::class);
    expect(ProductBatch::sole()->quantity)->toBe('10.1234')->and($this->line->fresh()->received_quantity)->toBe('10.1234');
    $this->assertDatabaseCount('purchase_receipts', 1);
    expect(fn () => $this->inventoryService->increaseStock($this->stock, $this->warehouse, '1', Movement::Purchase, $this->actor, unitCost: '99', batch: ['batch_number' => 'LOT-FIRST', 'expiration_date' => '2026-10-10']))->toThrow(ValidationException::class);
});

test('changing batch metadata cannot replay the same receipt key', function () {
    ($this->receive)($this->data);
    $data = $this->data;
    $data['items'][0]['batch_number'] = 'OTHER-LOT';
    expect(fn () => ($this->receive)($data))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('product_batches', 1);
});

test('decreases consume untracked stock then FIFO batches and preserve movement balance chains', function () {
    $this->inventoryService->increaseStock($this->stock, $this->warehouse, '2', Movement::Purchase, $this->actor);
    ($this->receive)($this->data);
    $this->inventoryService->decreaseStock($this->stock, $this->warehouse, '3.1234', Movement::Sale, $this->actor);
    expect(Inventory::sole()->quantity)->toBe('9.0000')->and(ProductBatch::sole()->quantity)->toBe('9.0000');
    $movements = InventoryMovement::where('type', Movement::Sale)->orderBy('id')->get();
    expect($movements)->toHaveCount(2)->and($movements[0]->product_batch_id)->toBeNull()
        ->and($movements[0]->quantity_delta)->toBe('-2.0000')->and($movements[1]->quantity_delta)->toBe('-1.1234')
        ->and($movements[0]->quantity_after)->toBe($movements[1]->quantity_before);
});

test('explicit batch allocation validates warehouse and available batch balance', function () {
    ($this->receive)($this->data);
    $batch = ProductBatch::sole();
    expect(fn () => $this->inventoryService->decreaseStock($this->stock, $this->warehouse, '1', Movement::Damage, $this->actor, batchId: 99999))->toThrow(ValidationException::class);
    $this->inventoryService->decreaseStock($this->stock, $this->warehouse, '0.1234', Movement::Damage, $this->actor, batchId: $batch->id);
    expect($batch->fresh()->quantity)->toBe('10.0000');
});

test('physical adjustments keep batches consistent and retain depleted history', function () {
    ($this->receive)($this->data);
    $this->inventoryService->adjustStock($this->stock, $this->warehouse, '5', $this->actor, 'Physical count');
    expect(ProductBatch::sole()->quantity)->toBe('5.0000');
    $this->inventoryService->adjustStock($this->stock, $this->warehouse, '7', $this->actor, 'Extra untracked stock');
    expect(ProductBatch::sole()->quantity)->toBe('5.0000');
    $this->inventoryService->adjustStock($this->stock, $this->warehouse, '0', $this->actor, 'Disposed damaged stock');
    expect(ProductBatch::sole()->quantity)->toBe('0.0000')->and(Inventory::sole()->quantity)->toBe('0.0000');
    expect(fn () => ProductBatch::sole()->delete())->toThrow(LogicException::class);
});

test('transfers preserve batch identity cost expiry and quantity in both warehouses', function () {
    $receipt = ($this->receive)($this->data);
    $destination = Warehouse::factory()->create();
    $this->inventoryService->transfer($this->stock, $this->warehouse, $destination, '4.1234', $this->actor, $receipt);
    $sourceBatch = ProductBatch::where('warehouse_id', $this->warehouse->id)->sole();
    $destinationBatch = ProductBatch::where('warehouse_id', $destination->id)->sole();
    expect($sourceBatch->quantity)->toBe('6.0000')->and($destinationBatch->quantity)->toBe('4.1234')
        ->and($destinationBatch->batch_number)->toBe($sourceBatch->batch_number)
        ->and($destinationBatch->expiration_date->format('Y-m-d'))->toBe('2026-10-10')
        ->and($destinationBatch->unit_cost)->toBe('12.3456');
    $this->assertDatabaseCount('inventory_movements', 3);
});

test('ledger failure rolls back batch inventory receipt and order updates', function () {
    InventoryMovement::creating(function (): void {
        throw new RuntimeException('Batch ledger failed');
    });
    try {
        expect(fn () => ($this->receive)($this->data))->toThrow(RuntimeException::class, 'Batch ledger failed');
    } finally {
        Event::forget('eloquent.creating: '.InventoryMovement::class);
        InventoryMovement::clearBootedModels();
    }
    foreach (['inventories', 'product_batches', 'purchase_receipts', 'purchase_receipt_items', 'inventory_movements'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
    expect($this->line->fresh()->received_quantity)->toBe('0.0000')->and($this->order->fresh()->status)->toBe(PurchaseOrderStatus::Ordered);
});

test('expiration reports have inclusive boundaries exclude depleted and undated stock and require permission', function () {
    $this->travelTo(now()->setDate(2026, 9, 20)->startOfDay());
    foreach ([-1 => 'EXPIRED', 0 => 'TODAY', 7 => 'SEVEN', 8 => 'EIGHT', 30 => 'THIRTY', 31 => 'LATER'] as $days => $number) {
        ProductBatch::factory()->create(['batch_number' => $number, 'quantity' => '1', 'expiration_date' => today()->addDays($days)]);
    }
    ProductBatch::factory()->create(['batch_number' => 'DEPLETED', 'quantity' => '0', 'expiration_date' => today()]);
    ProductBatch::factory()->create(['batch_number' => 'UNDATED', 'quantity' => '1', 'expiration_date' => null]);
    $this->get(route('product-batches.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('product-batches.index'))->assertForbidden();
    $this->actingAs($this->actor)->get(route('product-batches.index'))->assertOk();
    Livewire::test(ProductBatchTable::class)->set('period', '7')->assertSee('TODAY')->assertSee('SEVEN')
        ->assertDontSee('EXPIRED')->assertDontSee('EIGHT')->assertDontSee('DEPLETED')->assertDontSee('UNDATED')
        ->set('period', '30')->assertSee('THIRTY')->assertDontSee('LATER')
        ->set('period', 'expired')->assertSee('EXPIRED')->assertDontSee('TODAY')
        ->set('period', 'all')->set('search', 'UNDATED')->assertSee('UNDATED')->assertDontSee('THIRTY');
    $this->assertDatabaseCount('product_batches', 8);
});

test('receiving UI saves optional lot metadata', function () {
    Livewire::actingAs($this->actor)->test(PurchaseReceiving::class, ['order' => $this->order])
        ->set('quantities.'.$this->line->id, '5')->set('batchNumbers.'.$this->line->id, 'UI-LOT')
        ->set('expirationDates.'.$this->line->id, '2026-12-31')->call('receive')->assertHasNoErrors()->assertRedirect();
    expect(ProductBatch::sole()->batch_number)->toBe('UI-LOT');
});

test('batch permissions seed and grant safely', function () {
    $this->seed(ProductBatchPermissionSeeder::class);
    $this->artisan('batches:grant', ['email' => $this->actor->email])->assertSuccessful();
    $this->artisan('batches:grant', ['email' => 'missing@example.test'])->assertFailed();
    expect($this->actor->roles()->count())->toBe(2);
});

test('FIFO allocation spans lots without prematurely applying FEFO', function () {
    ($this->receive)($this->data);
    $data = [...$this->data, 'idempotency_key' => (string) Str::uuid()];
    $data['items'][0]['batch_number'] = 'SECOND-OLDER-EXPIRY';
    $data['items'][0]['expiration_date'] = '2026-09-01';
    ($this->receive)($data);
    $this->inventoryService->decreaseStock($this->stock, $this->warehouse, '11.1234', Movement::Sale, $this->actor);
    expect(ProductBatch::where('batch_number', 'LOT-FIRST')->sole()->quantity)->toBe('0.0000')
        ->and(ProductBatch::where('batch_number', 'SECOND-OLDER-EXPIRY')->sole()->quantity)->toBe('9.1234');
});

test('destination batch conflicts roll back source lot and paired movements', function () {
    $receipt = ($this->receive)($this->data);
    $destination = Warehouse::factory()->create();
    $this->inventoryService->increaseStock($this->stock, $destination, '1', Movement::Purchase, $this->actor,
        unitCost: '99', batch: ['batch_number' => 'LOT-FIRST', 'expiration_date' => '2026-10-10']);
    expect(fn () => $this->inventoryService->transfer($this->stock, $this->warehouse, $destination, '2', $this->actor, $receipt))->toThrow(ValidationException::class);
    expect(ProductBatch::where('warehouse_id', $this->warehouse->id)->sole()->quantity)->toBe('10.1234')
        ->and(Inventory::where('warehouse_id', $this->warehouse->id)->sole()->quantity)->toBe('10.1234');
    $this->assertDatabaseCount('inventory_movements', 2);
});

test('reserved quantities cannot be consumed through batch selection', function () {
    ($this->receive)($this->data);
    Inventory::sole()->forceFill(['reserved_quantity' => '10'])->save();
    expect(fn () => $this->inventoryService->decreaseStock($this->stock, $this->warehouse, '1', Movement::Sale, $this->actor, batchId: ProductBatch::sole()->id))->toThrow(ValidationException::class);
    expect(ProductBatch::sole()->quantity)->toBe('10.1234');
});

test('batch migrations retain existing lot quantities and expiration dates', function () {
    $migration = require database_path('migrations/2026_09_20_012122_extend_product_batches_for_tracking.php');
    $migration->down();
    DB::table('product_batches')->insert([
        'stock_item_id' => $this->stock->id, 'warehouse_id' => $this->warehouse->id,
        'batch_number' => 'LEGACY', 'quantity' => '3.1234', 'unit_cost' => '2.5000', 'expires_at' => '2027-01-01',
    ]);
    $migration->up();
    expect(ProductBatch::sole()->expiration_date->format('Y-m-d'))->toBe('2027-01-01')
        ->and(ProductBatch::sole()->quantity)->toBe('3.1234');
});
