<?php

use App\InventoryMovementType;
use App\Livewire\PurchaseReceiving;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\PurchaseOrderStatus;
use App\Services\InventoryService;
use App\Services\PurchaseOrderService;
use App\Services\ReceivingService;
use Database\Seeders\PurchasingPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(PurchasingPermissionSeeder::class);
    $this->receiver = User::factory()->create(['is_active' => true]);
    $this->receiver->roles()->attach(Role::where('slug', 'purchasing-manager')->sole());
    $this->stockItem = StockItem::factory()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->orderData = [
        'supplier_id' => Supplier::factory()->create()->id,
        'warehouse_id' => $this->warehouse->id,
        'ordered_at' => today()->format('Y-m-d'),
        'items' => [[
            'stock_item_id' => $this->stockItem->id, 'ordered_quantity' => '100',
            'unit_cost' => '12.3456', 'discount' => '0', 'tax' => '0',
        ]],
    ];
    $this->orders = app(PurchaseOrderService::class);
    $this->receiving = app(ReceivingService::class);
    $this->order = $this->orders->saveDraft($this->orderData, $this->receiver);
    $this->order = $this->orders->transition($this->order, PurchaseOrderStatus::Ordered, $this->receiver);
    $this->line = $this->order->items()->sole();
    $this->receiptData = fn (string $quantity): array => [
        'idempotency_key' => (string) Str::uuid(),
        'items' => [['purchase_order_item_id' => $this->line->id, 'quantity' => $quantity]],
    ];
});

test('full receiving delegates to inventory service and creates an attributable purchase movement', function () {
    $this->assertDatabaseCount('inventories', 0);
    $this->assertDatabaseCount('inventory_movements', 0);
    $this->partialMock(InventoryService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('increaseStock')->once()->passthru();
    });

    $receipt = $this->receiving->receive($this->order, ($this->receiptData)('100'), $this->receiver);

    expect($this->order->fresh()->status)->toBe(PurchaseOrderStatus::Received)
        ->and($this->line->fresh()->received_quantity)->toBe('100.0000')
        ->and($this->line->fresh()->remainingQuantity())->toBe('0.0000')
        ->and(Inventory::sole()->quantity)->toBe('100.0000')
        ->and(Inventory::sole()->warehouse_id)->toBe($this->warehouse->id);
    $movement = InventoryMovement::sole();
    expect($movement->type)->toBe(InventoryMovementType::Purchase)
        ->and($movement->stock_item_id)->toBe($this->stockItem->id)
        ->and($movement->quantity_before)->toBe('0.0000')
        ->and($movement->quantity_delta)->toBe('100.0000')
        ->and($movement->quantity_after)->toBe('100.0000')
        ->and($movement->performed_by)->toBe($this->receiver->id)
        ->and($movement->unit_cost)->toBe('12.3456')
        ->and($movement->reference_type)->toBe(PurchaseReceipt::class)
        ->and($movement->reference_id)->toBe($receipt->id);
    expect($receipt->items()->sole()->quantity)->toBe('100.0000');
});

test('receiving screen displays one hundred ordered sixty received and forty remaining then completes the order', function () {
    $this->receiving->receive($this->order, ($this->receiptData)('60'), $this->receiver);
    expect($this->order->fresh()->status)->toBe(PurchaseOrderStatus::PartiallyReceived);

    $component = Livewire::actingAs($this->receiver)->test(PurchaseReceiving::class, ['order' => $this->order])
        ->assertSeeInOrder(['Ordered', 'Received', 'Remaining', 'Receive now'])
        ->assertSee('100.0000')->assertSee('60.0000')->assertSee('40.0000')
        ->assertSeeHtml('max="40.0000"')
        ->assertSet("quantities.{$this->line->id}", '')
        ->set("quantities.{$this->line->id}", '40')->call('receive')->assertHasNoErrors();

    $receipt = PurchaseReceipt::latest('id')->firstOrFail();
    $component->assertRedirect(route('purchase-orders.receipt', [$this->order, $receipt]));
    expect($this->order->fresh()->status)->toBe(PurchaseOrderStatus::Received)
        ->and($this->line->fresh()->received_quantity)->toBe('100.0000')
        ->and($this->line->fresh()->remainingQuantity())->toBe('0.0000')
        ->and(Inventory::sole()->quantity)->toBe('100.0000');
    expect(InventoryMovement::orderBy('id')->pluck('quantity_delta')->all())->toBe(['60.0000', '40.0000']);
    $this->assertDatabaseCount('purchase_receipts', 2);
    $this->actingAs($this->receiver)->get(route('purchase-orders.show', $this->order))
        ->assertOk()->assertSee('RECEIVED')->assertSee('0.0000')->assertDontSee('Receive products');
});

test('invalid receipts after a partial delivery preserve the remaining stock and receipt history', function (string $quantity) {
    $this->receiving->receive($this->order, ($this->receiptData)('60'), $this->receiver);

    expect(fn () => $this->receiving->receive($this->order, ($this->receiptData)($quantity), $this->receiver))
        ->toThrow(ValidationException::class);

    expect($this->order->fresh()->status)->toBe(PurchaseOrderStatus::PartiallyReceived)
        ->and($this->line->fresh()->received_quantity)->toBe('60.0000')
        ->and($this->line->fresh()->remainingQuantity())->toBe('40.0000')
        ->and(Inventory::sole()->quantity)->toBe('60.0000');
    $this->assertDatabaseCount('purchase_receipts', 1);
    $this->assertDatabaseCount('purchase_receipt_items', 1);
    $this->assertDatabaseCount('inventory_movements', 1);
})->with(['40.0001', '41', '100', '0', '-1', 'abc', '1.12345']);

test('failure updating the final status rolls back stock movements and receipt but preserves earlier deliveries', function () {
    $this->receiving->receive($this->order, ($this->receiptData)('60'), $this->receiver);
    $revision = $this->order->fresh()->revision;
    Event::listen('eloquent.updating: '.PurchaseOrder::class, function (PurchaseOrder $order): void {
        if ($order->status === PurchaseOrderStatus::Received) {
            throw new RuntimeException('Final receiving status failed');
        }
    });
    try {
        expect(fn () => $this->receiving->receive($this->order, ($this->receiptData)('40'), $this->receiver))
            ->toThrow(RuntimeException::class, 'Final receiving status failed');
    } finally {
        Event::forget('eloquent.updating: '.PurchaseOrder::class);
    }
    expect($this->order->fresh()->status)->toBe(PurchaseOrderStatus::PartiallyReceived)
        ->and($this->order->fresh()->revision)->toBe($revision)
        ->and($this->line->fresh()->received_quantity)->toBe('60.0000')
        ->and($this->line->fresh()->remainingQuantity())->toBe('40.0000')
        ->and(Inventory::sole()->quantity)->toBe('60.0000');
    $this->assertDatabaseCount('purchase_receipts', 1);
    $this->assertDatabaseCount('purchase_receipt_items', 1);
    $this->assertDatabaseCount('inventory_movements', 1);
});

test('an order remains partially received until every line is complete and blank lines are skipped', function () {
    $second = StockItem::factory()->create();
    $data = $this->orderData;
    $data['items'][] = [...$data['items'][0], 'stock_item_id' => $second->id, 'ordered_quantity' => '5'];
    $order = $this->orders->saveDraft($data, $this->receiver);
    $this->orders->transition($order, PurchaseOrderStatus::Ordered, $this->receiver);
    $firstLine = $order->items()->where('stock_item_id', $this->stockItem->id)->sole();
    $secondLine = $order->items()->where('stock_item_id', $second->id)->sole();

    Livewire::actingAs($this->receiver)->test(PurchaseReceiving::class, ['order' => $order])
        ->set("quantities.$firstLine->id", '100')->call('receive')->assertHasNoErrors();
    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::PartiallyReceived)
        ->and($secondLine->fresh()->received_quantity)->toBe('0.0000');
    $this->assertDatabaseMissing('inventories', ['stock_item_id' => $second->id]);

    Livewire::test(PurchaseReceiving::class, ['order' => $order])
        ->assertSee('Complete')->assertDontSeeHtml('id="receive-'.$firstLine->id.'"')
        ->set("quantities.$secondLine->id", '5')->call('receive')->assertHasNoErrors();
    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Received)
        ->and($order->items()->get()->every(fn (PurchaseOrderItem $item): bool => $item->remainingQuantity() === '0.0000'))->toBeTrue();
    $this->assertDatabaseCount('inventory_movements', 2);
});

test('a stale receiving screen rejects a quantity that exceeds the newly reduced remainder', function () {
    $component = Livewire::actingAs($this->receiver)->test(PurchaseReceiving::class, ['order' => $this->order])
        ->set("quantities.{$this->line->id}", '100');
    $this->receiving->receive($this->order, ($this->receiptData)('60'), $this->receiver);

    $component->call('receive')->assertHasErrors('items')->assertSeeHtml('max="40.0000"');
    expect(Inventory::sole()->quantity)->toBe('60.0000');
    $this->assertDatabaseCount('purchase_receipts', 1);
});

test('receiving rechecks permissions if they are revoked while the form is open', function () {
    $component = Livewire::actingAs($this->receiver)->test(PurchaseReceiving::class, ['order' => $this->order])
        ->set("quantities.{$this->line->id}", '100');
    $this->receiver->roles()->detach();
    $component->call('receive')->assertForbidden();
    $this->assertDatabaseCount('inventories', 0);
    $this->assertDatabaseCount('purchase_receipts', 0);
});
