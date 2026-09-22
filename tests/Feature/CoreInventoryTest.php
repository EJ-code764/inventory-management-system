<?php

use App\InventoryMovementType as Movement;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\StockItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->item = StockItem::factory()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->actor = User::factory()->create(['is_active' => true]);
    $this->service = app(InventoryService::class);
});

test('increases and decreases persist exact balances and complete movement metadata', function () {
    $this->service->increaseStock($this->item, $this->warehouse, '10.1250', Movement::Purchase, $this->actor, 'purchase_receipt', 123, 'Received', '8.1234');
    $inventory = $this->service->decreaseStock($this->item, $this->warehouse, '2.0001', Movement::Sale, $this->actor, 'sale', 321, 'POS contract', '8.1234');
    expect($inventory->quantity)->toBe('8.1249')->and($inventory->availableQuantity())->toBe('8.1249');
    $movement = InventoryMovement::latest('id')->firstOrFail();
    expect($movement->type)->toBe(Movement::Sale)
        ->and($movement->quantity_delta)->toBe('-2.0001')->and($movement->quantity_before)->toBe('10.1250')
        ->and($movement->quantity_after)->toBe('8.1249')->and($movement->unit_cost)->toBe('8.1234')
        ->and($movement->performed_by)->toBe($this->actor->id)->and($movement->reference_type)->toBe('sale')
        ->and($movement->reference_id)->toBe(321)->and($movement->reason)->toBe('POS contract')
        ->and($movement->occurred_at)->not->toBeNull();
    $this->assertDatabaseCount('inventories', 1);
    $this->assertDatabaseCount('inventory_movements', 2);
});

test('fractional arithmetic never silently rounds', function () {
    $this->service->increaseStock($this->item, $this->warehouse, '0.3000', Movement::Purchase, $this->actor);
    $this->service->decreaseStock($this->item, $this->warehouse, '0.1000', Movement::Sale, $this->actor);
    $balance = $this->service->decreaseStock($this->item, $this->warehouse, '0.2000', Movement::Sale, $this->actor);
    expect($balance->quantity)->toBe('0.0000');
});

test('invalid positive quantities are rejected without records', function (string|float $quantity) {
    foreach (['increaseStock', 'decreaseStock'] as $method) {
        expect(fn () => $this->service->{$method}($this->item, $this->warehouse, $quantity, $method === 'increaseStock' ? Movement::Purchase : Movement::Sale, $this->actor))
            ->toThrow(ValidationException::class);
    }
    $this->assertDatabaseCount('inventories', 0);
    $this->assertDatabaseCount('inventory_movements', 0);
})->with(['0', '-1', '0.00001', '1.12345', '10000000000000000', '1e3', '', 'abc', INF, NAN]);

test('insufficient stock including first-use decreases leaves no balance or movement', function () {
    expect(fn () => $this->service->decreaseStock($this->item, $this->warehouse, '1', Movement::Sale, $this->actor))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('inventories', 0);
    $this->assertDatabaseCount('inventory_movements', 0);
});

test('reserved quantities cannot be consumed or adjusted away', function () {
    $inventory = Inventory::factory()->create(['stock_item_id' => $this->item->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => '5.0000', 'reserved_quantity' => '3.0000']);
    expect(fn () => $this->service->decreaseStock($this->item, $this->warehouse, '2.0001', Movement::Sale, $this->actor))->toThrow(ValidationException::class);
    expect(fn () => $this->service->adjustStock($this->item, $this->warehouse, '2', $this->actor, 'Count'))->toThrow(ValidationException::class);
    $result = $this->service->decreaseStock($this->item, $this->warehouse, '2', Movement::Sale, $this->actor);
    expect($result->quantity)->toBe('3.0000')->and($result->availableQuantity())->toBe('0.0000')
        ->and($inventory->fresh()->reserved_quantity)->toBe('3.0000');
});

test('physical-count adjustments derive signed deltas and require a reason', function () {
    $this->service->adjustStock($this->item, $this->warehouse, '5.5', $this->actor, 'Opening count', 'stock_count', 1);
    $this->service->adjustStock($this->item, $this->warehouse, '2.25', $this->actor, 'Recount', 'stock_count', 2);
    $this->service->adjustStock($this->item, $this->warehouse, '0', $this->actor, 'Empty shelf', 'stock_count', 3);
    expect(InventoryMovement::orderBy('id')->pluck('quantity_delta')->all())->toBe(['5.5000', '-3.2500', '-2.2500']);
    expect(InventoryMovement::latest('id')->first()->type)->toBe(Movement::AdjustmentOut);
    expect(fn () => $this->service->adjustStock($this->item, $this->warehouse, '1', $this->actor, ' '))->toThrow(ValidationException::class);
    expect(fn () => $this->service->adjustStock($this->item, $this->warehouse, '0', $this->actor, 'No change'))->toThrow(ValidationException::class);
    expect(fn () => $this->service->adjustStock($this->item, $this->warehouse, '-1', $this->actor, 'Invalid count'))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('inventory_movements', 3);
});

test('every supported movement type has a validated direction', function (Movement $type) {
    $incoming = in_array($type, [Movement::Purchase, Movement::SaleReturn, Movement::AdjustmentIn, Movement::TransferIn], true);
    if (! $incoming) {
        $this->service->increaseStock($this->item, $this->warehouse, '10', Movement::Purchase, $this->actor);
    }
    $method = $incoming ? 'increaseStock' : 'decreaseStock';
    $this->service->{$method}($this->item, $this->warehouse, '1', $type, $this->actor, remarks: 'Movement test');
    expect(InventoryMovement::latest('id')->first()->type)->toBe($type);
    $invalidMethod = $incoming ? 'decreaseStock' : 'increaseStock';
    expect(fn () => $this->service->{$invalidMethod}($this->item, $this->warehouse, '1', $type, $this->actor))->toThrow(ValidationException::class);
})->with(Movement::cases());

test('variants and warehouses maintain independent unique balances', function () {
    $variant = ProductVariant::factory()->create();
    $variantItem = StockItem::factory()->create(['product_id' => null, 'product_variant_id' => $variant->id]);
    $other = Warehouse::factory()->create();
    $this->service->increaseStock($this->item, $this->warehouse, '3', Movement::Purchase, $this->actor);
    $this->service->increaseStock($this->item, $other, '9', Movement::Purchase, $this->actor);
    $this->service->increaseStock($variantItem, $this->warehouse, '4', Movement::Purchase, $this->actor);
    expect(Inventory::where('stock_item_id', $this->item->id)->where('warehouse_id', $this->warehouse->id)->sole()->quantity)->toBe('3.0000');
    $this->assertDatabaseCount('inventories', 3);
    expect(fn () => Inventory::create(['stock_item_id' => $this->item->id, 'warehouse_id' => $this->warehouse->id]))->toThrow(QueryException::class);
});

test('a failed movement write rolls back existing or newly created balances', function (bool $existing) {
    if ($existing) {
        $this->service->increaseStock($this->item, $this->warehouse, '3', Movement::Purchase, $this->actor);
    }
    Event::listen('eloquent.creating: '.InventoryMovement::class, function (): void {
        throw new RuntimeException('Simulated ledger failure');
    });
    try {
        expect(fn () => $this->service->increaseStock($this->item, $this->warehouse, '2', Movement::Purchase, $this->actor))->toThrow(RuntimeException::class, 'Simulated ledger failure');
    } finally {
        Event::forget('eloquent.creating: '.InventoryMovement::class);
    }
    $this->assertDatabaseCount('inventories', $existing ? 1 : 0);
    $this->assertDatabaseCount('inventory_movements', $existing ? 1 : 0);
    if ($existing) {
        expect(Inventory::sole()->quantity)->toBe('3.0000');
    }
})->with([true, false]);

test('outer workflow rollback reverses inventory and ledger together', function () {
    expect(function () {
        DB::transaction(function () {
            $this->service->decreaseStock($this->item, $this->warehouse, '1', Movement::Sale, $this->actor, 'sale', 50);
            throw new RuntimeException('Sale failed');
        });
    })->toThrow(ValidationException::class);
    $this->service->increaseStock($this->item, $this->warehouse, '5', Movement::Purchase, $this->actor);
    expect(function () {
        DB::transaction(function () {
            $this->service->decreaseStock($this->item, $this->warehouse, '1', Movement::Sale, $this->actor, 'sale', 50);
            throw new RuntimeException('Sale failed');
        });
    })->toThrow(RuntimeException::class, 'Sale failed');
    expect(Inventory::sole()->quantity)->toBe('5.0000');
    $this->assertDatabaseCount('inventory_movements', 1);
});

test('movements cannot be edited or deleted through models and balances reject mass assignment', function () {
    $inventory = $this->service->increaseStock($this->item, $this->warehouse, '2', Movement::Purchase, $this->actor);
    $inventory->fill(['quantity' => 99, 'reserved_quantity' => 99])->save();
    expect($inventory->fresh()->quantity)->toBe('2.0000')->and($inventory->fresh()->reserved_quantity)->toBe('0.0000');
    $movement = InventoryMovement::sole();
    expect(fn () => $movement->forceFill(['quantity_delta' => '99'])->save())->toThrow(LogicException::class);
    expect(fn () => $movement->delete())->toThrow(LogicException::class);
    expect($movement->fresh()->quantity_delta)->toBe('2.0000');
});

test('responsible actors are mandatory and inactive actors are rejected', function () {
    expect(fn () => $this->service->increase($this->item, $this->warehouse, 1, Movement::Purchase))->toThrow(ValidationException::class);
    $this->actor->update(['is_active' => false]);
    expect(fn () => $this->service->increaseStock($this->item, $this->warehouse, 1, Movement::Purchase, $this->actor))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('inventories', 0);
});

test('inactive items warehouses and products cannot change stock', function (string $target) {
    match ($target) {
        'warehouse' => $this->warehouse->update(['status' => 'inactive']),
        'item' => $this->item->update(['is_active' => false]),
        'product' => $this->item->product->update(['status' => 'inactive']),
    };
    expect(fn () => $this->service->increaseStock($this->item, $this->warehouse, 1, Movement::Purchase, $this->actor))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('inventory_movements', 0);
})->with(['warehouse', 'item', 'product']);

test('invalid metadata and costs are rejected before mutation', function () {
    expect(fn () => $this->service->increaseStock($this->item, $this->warehouse, 1, Movement::Purchase, $this->actor, 'sale', null))->toThrow(ValidationException::class);
    expect(fn () => $this->service->increaseStock($this->item, $this->warehouse, 1, Movement::Purchase, $this->actor, '', 1))->toThrow(ValidationException::class);
    expect(fn () => $this->service->increaseStock($this->item, $this->warehouse, 1, Movement::Purchase, $this->actor, 'sale', 0))->toThrow(ValidationException::class);
    expect(fn () => $this->service->increaseStock($this->item, $this->warehouse, 1, Movement::Purchase, $this->actor, unitCost: '-1'))->toThrow(ValidationException::class);
    expect(fn () => $this->service->increaseStock($this->item, $this->warehouse, 1, Movement::Purchase, $this->actor, remarks: str_repeat('x', 256)))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('inventories', 0);
});

test('warehouse reorder overrides fall back to catalog values without copying them', function () {
    $inventory = $this->service->increaseStock($this->item, $this->warehouse, '5', Movement::Purchase, $this->actor);
    expect($inventory->effectiveReorderLevel())->toBe('5.0000')->and($inventory->isLowStock())->toBeTrue();
    $inventory->update(['reorder_level' => '0']);
    expect($inventory->effectiveReorderLevel())->toBe('0.0000')->and($inventory->isLowStock())->toBeFalse();
});
