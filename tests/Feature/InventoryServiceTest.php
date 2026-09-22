<?php

use App\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\StockItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function stockItemForInventoryTest(): StockItem
{
    $categoryId = DB::table('categories')->insertGetId(['name' => 'General', 'slug' => 'general', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    $unitId = DB::table('units')->insertGetId(['name' => 'Piece', 'code' => 'pc', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    $productId = DB::table('products')->insertGetId(['category_id' => $categoryId, 'unit_id' => $unitId, 'name' => 'Test Product', 'status' => 'active', 'has_variants' => false, 'created_at' => now(), 'updated_at' => now()]);

    return StockItem::query()->forceCreate(['product_id' => $productId, 'sku' => 'TEST-SKU', 'cost_price' => 10, 'selling_price' => 20, 'reorder_level' => 2, 'is_active' => true]);
}

test('inventory changes create an immutable movement', function () {
    $item = stockItemForInventoryTest();
    $warehouse = Warehouse::query()->create(['name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
    $actor = User::factory()->create();

    app(InventoryService::class)->increase($item, $warehouse, 5, InventoryMovementType::Purchase, $actor, unitCost: 10);
    $inventory = app(InventoryService::class)->decrease($item, $warehouse, 2, InventoryMovementType::Damage, $actor, reason: 'Damaged');

    expect((float) $inventory->quantity)->toBe(3.0);
    expect(InventoryMovement::query()->count())->toBe(2);
    expect(InventoryMovement::query()->latest('id')->first()->type)->toBe(InventoryMovementType::Damage);
});

test('inventory cannot be decreased below available stock', function () {
    $item = stockItemForInventoryTest();
    $warehouse = Warehouse::query()->create(['name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);

    $actor = User::factory()->create(['is_active' => true]);
    app(InventoryService::class)->increase($item, $warehouse, 1, InventoryMovementType::Purchase, $actor);

    expect(fn () => app(InventoryService::class)->decrease($item, $warehouse, 2, InventoryMovementType::Damage, $actor))->toThrow(ValidationException::class);
    expect(InventoryMovement::query()->count())->toBe(1);
});
