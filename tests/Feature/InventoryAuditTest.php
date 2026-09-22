<?php

use App\InventoryMovementType as Movement;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrderItem;
use App\Models\StockItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\ProductService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('sample account seeding is development only and idempotent', function (string $environment, int $count) {
    app()->instance('env', $environment);
    app(DatabaseSeeder::class)->__invoke();
    app(DatabaseSeeder::class)->__invoke();
    expect(User::where('email', 'test@example.com')->count())->toBe($count);
})->with([['production', 0], ['staging', 0], ['local', 1]]);

test('login attempts are rate limited and recover after the window expires', function () {
    $this->withoutVite();
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('login.store'), ['email' => 'audit@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
    }
    $this->post(route('login.store'), ['email' => 'AUDIT@example.test', 'password' => 'wrong'])->assertStatus(429);
    $this->travel(61)->seconds();
    $this->post(route('login.store'), ['email' => 'audit@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
});

test('product units cannot reinterpret inventory or purchase history', function (string $usage) {
    $item = StockItem::factory()->create();
    $product = $item->product;
    if ($usage === 'inventory') {
        app(InventoryService::class)->increaseStock($item, Warehouse::factory()->create(), '5', Movement::Purchase, User::factory()->create());
    } else {
        PurchaseOrderItem::factory()->create(['stock_item_id' => $item->id]);
    }
    $data = [...$product->only(['name', 'category_id', 'brand_id', 'unit_id']),
        ...$item->only(['sku', 'barcode', 'cost_price', 'selling_price', 'reorder_level']), 'status' => 'active'];
    expect(fn () => app(ProductService::class)->save([...$data, 'unit_id' => Unit::factory()->create()->id], $product))->toThrow(ValidationException::class);
    expect($product->fresh()->unit_id)->toBe($product->unit_id);
    expect(app(ProductService::class)->save([...$data, 'name' => 'Safe edit'], $product)->name)->toBe('Safe edit');
})->with(['inventory', 'purchase']);

test('unused products can correct their unit without replacing stock identity', function () {
    $item = StockItem::factory()->create();
    $product = $item->product;
    $unit = Unit::factory()->create();
    $data = [...$product->only(['name', 'category_id', 'brand_id']),
        ...$item->only(['sku', 'barcode', 'cost_price', 'selling_price', 'reorder_level']), 'unit_id' => $unit->id, 'status' => 'active'];
    $saved = app(ProductService::class)->save($data, $product);
    expect($saved->unit_id)->toBe($unit->id)->and($saved->stockItem->id)->toBe($item->id);
});

test('a stock identity cannot be reassigned to a different product', function () {
    $item = StockItem::factory()->create();
    expect(fn () => $item->update(['product_id' => Product::factory()->create()->id]))->toThrow(LogicException::class);
    expect($item->fresh()->product_id)->toBe($item->getRawOriginal('product_id'));
});

test('products expose their existing variants through the inverse relationship', function () {
    $variant = ProductVariant::factory()->create();
    expect($variant->product->variants()->sole()->id)->toBe($variant->id);
    expect(fn () => $variant->forceFill(['product_id' => Product::factory()->create()->id])->save())->toThrow(LogicException::class);
});

test('multi lot transfers load batch metadata without per movement lazy queries', function () {
    $item = StockItem::factory()->create();
    $actor = User::factory()->create();
    $source = Warehouse::factory()->create();
    $destination = Warehouse::factory()->create();
    $service = app(InventoryService::class);
    foreach (['A', 'B'] as $lot) {
        $service->increaseStock($item, $source, '2', Movement::Purchase, $actor, unitCost: '1', batch: ['batch_number' => $lot]);
    }
    Model::preventLazyLoading();
    try {
        $service->transfer($item, $source, $destination, '4', $actor, $source);
    } finally {
        Model::preventLazyLoading(false);
    }
    expect(Inventory::where('warehouse_id', $source->id)->sole()->quantity)->toBe('0.0000')
        ->and(Inventory::where('warehouse_id', $destination->id)->sole()->quantity)->toBe('4.0000')
        ->and(InventoryMovement::where('type', Movement::TransferOut)->count())->toBe(2)
        ->and(InventoryMovement::where('type', Movement::TransferIn)->count())->toBe(2);
});
