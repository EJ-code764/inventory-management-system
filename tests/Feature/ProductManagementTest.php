<?php

use App\Livewire\ProductTable;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\Unit;
use App\Models\User;
use App\ProductStatus;
use App\Services\ProductService;
use Database\Seeders\ProductPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(ProductPermissionSeeder::class);
    $this->manager = User::factory()->create(['is_active' => true]);
    $this->manager->roles()->attach(Role::where('slug', 'product-manager')->firstOrFail());
    $this->data = [
        'name' => 'Coffee beans',
        'description' => 'Freshly roasted',
        'sku' => 'COFFEE-001',
        'barcode' => '0012345678901',
        'category_id' => Category::factory()->create()->id,
        'brand_id' => Brand::factory()->create()->id,
        'unit_id' => Unit::factory()->create()->id,
        'cost_price' => '123.4567',
        'selling_price' => '150.0000',
        'reorder_level' => '5.2500',
        'status' => 'active',
    ];
});

test('managers can create view edit and deactivate products without changing inventory', function () {
    $this->actingAs($this->manager);
    $this->get(route('products.index'))->assertOk()->assertSeeLivewire(ProductTable::class);
    $this->get(route('products.create'))->assertOk();
    $this->post(route('products.store'), [...$this->data, 'quantity' => 999, 'has_variants' => true])
        ->assertSessionHasNoErrors();
    $product = Product::firstOrFail();
    expect($product->stockItem->sku)->toBe('COFFEE-001')
        ->and($product->stockItem->barcode)->toBe('0012345678901')
        ->and($product->stockItem->cost_price)->toBe('123.4567')
        ->and($product->has_variants)->toBeFalse()
        ->and($product->category->id)->toBe($this->data['category_id'])
        ->and($product->brand->id)->toBe($this->data['brand_id'])
        ->and($product->unit->id)->toBe($this->data['unit_id']);
    $stockItemId = $product->stockItem->id;
    $this->get(route('products.show', $product))->assertOk()->assertSee('Coffee beans')->assertSee('123.4567');
    $this->get(route('products.edit', $product))->assertOk()->assertSee('COFFEE-001');
    $this->put(route('products.update', $product), [...$this->data, 'name' => 'Updated coffee'])
        ->assertSessionHasNoErrors()->assertRedirect(route('products.show', $product));
    expect($product->fresh()->name)->toBe('Updated coffee')
        ->and($product->fresh()->stockItem->id)->toBe($stockItemId);
    foreach (['inactive', 'active'] as $status) {
        $this->patch(route('products.status', $product), ['status' => $status])->assertSessionHasNoErrors();
        expect($product->fresh()->status->value)->toBe($status)
            ->and($product->fresh()->stockItem->is_active)->toBe($status === 'active');
    }
    $this->assertDatabaseCount('products', 1);
    $this->assertDatabaseCount('stock_items', 1);
    $this->assertDatabaseCount('inventories', 0);
    $this->assertDatabaseCount('inventory_movements', 0);
});

test('guests and unauthorized users cannot read or mutate products', function () {
    $product = Product::factory()->withStockItem()->create();
    $this->get(route('products.index'))->assertRedirect(route('login'));
    $this->post(route('products.store'), $this->data)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['is_active' => true]));
    foreach (['index', 'create', 'show', 'edit'] as $action) {
        $this->get(route('products.'.$action, $product))->assertForbidden();
    }
    $this->post(route('products.store'), $this->data)->assertForbidden();
    $this->put(route('products.update', $product), $this->data)->assertForbidden();
    $this->patch(route('products.status', $product), ['status' => 'inactive'])->assertForbidden();
    Livewire::test(ProductTable::class)->assertForbidden();
});

test('view permission does not grant write access and inactive users are denied', function () {
    $role = Role::create(['name' => 'Product Viewer', 'slug' => 'product-viewer']);
    $role->permissions()->attach(Permission::where('slug', 'products.view')->firstOrFail());
    $this->manager->roles()->sync([$role->id]);
    $product = Product::factory()->withStockItem()->create();
    $this->actingAs($this->manager)->get(route('products.index'))->assertOk()->assertDontSee('Add product');
    $this->get(route('products.show', $product))->assertOk()->assertDontSee('Edit product');
    $this->post(route('products.store'), $this->data)->assertForbidden();
    $this->put(route('products.update', $product), $this->data)->assertForbidden();
    $this->patch(route('products.status', $product), ['status' => 'inactive'])->assertForbidden();
    $this->manager->update(['is_active' => false]);
    $this->get(route('products.index'))->assertForbidden();
    Livewire::actingAs($this->manager)->test(ProductTable::class)->assertForbidden();
});

test('product validation rejects missing invalid and oversized values', function () {
    $this->actingAs($this->manager)->post(route('products.store'), [
        'name' => ' ', 'sku' => '', 'category_id' => 9999, 'brand_id' => 9999, 'unit_id' => 9999,
        'status' => 'unknown', 'cost_price' => -1, 'selling_price' => -1, 'reorder_level' => -1,
    ])->assertSessionHasErrors(['name', 'sku', 'category_id', 'brand_id', 'unit_id', 'status', 'cost_price', 'selling_price', 'reorder_level']);
    $this->post(route('products.store'), [
        ...$this->data, 'sku' => str_repeat('s', 256), 'barcode' => str_repeat('b', 256),
        'name' => str_repeat('n', 256), 'description' => str_repeat('d', 5001),
    ])->assertSessionHasErrors(['sku', 'barcode', 'name', 'description']);
    $this->assertDatabaseCount('products', 0);
});

test('decimal fields reject negative excess precision overflow and exponent notation', function (string $field, string $value) {
    $this->actingAs($this->manager)->post(route('products.store'), [...$this->data, $field => $value])
        ->assertSessionHasErrors($field);
    $this->assertDatabaseCount('products', 0);
})->with(['cost_price', 'selling_price', 'reorder_level'])->with(['-0.0001', '1.12345', '10000000000000000', '1e2', 'not numeric']);

test('unique identities include variant stock items and ignore only the current stock item', function () {
    $product = Product::factory()->withStockItem()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'Large']);
    StockItem::create(['product_variant_id' => $variant->id, 'sku' => $this->data['sku'], 'barcode' => $this->data['barcode']]);
    $this->actingAs($this->manager)->post(route('products.store'), $this->data)->assertSessionHasErrors(['sku', 'barcode']);
    $this->put(route('products.update', $product), $this->data)->assertSessionHasErrors(['sku', 'barcode']);
    $this->put(route('products.update', $product), [...$this->data, 'sku' => $product->stockItem->sku, 'barcode' => null])->assertSessionHasNoErrors();
});

test('nullable barcode optional brand whitespace and zero prices are supported', function () {
    $this->actingAs($this->manager);
    foreach ([' FIRST ', 'SECOND'] as $sku) {
        $this->post(route('products.store'), [
            ...$this->data, 'sku' => $sku, 'barcode' => ' ', 'brand_id' => '', 'name' => ' Trimmed ',
            'cost_price' => '0', 'selling_price' => '0', 'reorder_level' => '0',
        ])->assertSessionHasNoErrors();
    }
    $this->assertDatabaseCount('products', 2);
    $this->assertDatabaseHas('stock_items', ['sku' => 'FIRST', 'barcode' => null]);
    $this->assertDatabaseHas('products', ['name' => 'Trimmed', 'brand_id' => null]);
});

test('inactive classifications cannot be newly selected but existing links remain editable', function (string $field, string $model) {
    $product = app(ProductService::class)->save($this->data);
    $model::findOrFail($this->data[$field])->update(['status' => 'inactive']);
    $this->actingAs($this->manager)->post(route('products.store'), [...$this->data, 'sku' => 'NEW', 'barcode' => null])
        ->assertSessionHasErrors($field);
    $this->get(route('products.edit', $product))->assertOk()->assertSee('(inactive)');
    $this->put(route('products.update', $product), [...$this->data, 'name' => 'Still editable'])->assertSessionHasNoErrors();
    $other = $model::factory()->create(['status' => 'inactive']);
    $this->put(route('products.update', $product), [...$this->data, $field => $other->id])->assertSessionHasErrors($field);
})->with([['category_id', Category::class], ['brand_id', Brand::class], ['unit_id', Unit::class]]);

test('stock item failures roll back both creation and product edits', function () {
    $first = app(ProductService::class)->save($this->data);
    expect(fn () => app(ProductService::class)->save([...$this->data, 'name' => 'Rolled back']))
        ->toThrow(ValidationException::class);
    $second = Product::factory()->withStockItem()->create(['name' => 'Original name']);
    expect(fn () => app(ProductService::class)->save([...$this->data, 'name' => 'Rolled back'], $second))
        ->toThrow(ValidationException::class);
    expect($second->fresh()->name)->toBe('Original name');
    $this->assertDatabaseCount('products', 2);
    $this->assertDatabaseCount('stock_items', 2);
});

test('livewire searches name sku and barcode with combined filters and resets pagination', function () {
    $target = app(ProductService::class)->save($this->data);
    Product::factory()->count(16)->withStockItem()->create();
    Product::factory()->withStockItem()->create(['name' => 'Coffee in another category']);
    $component = Livewire::actingAs($this->manager)->test(ProductTable::class)
        ->assertViewHas('products', fn ($products) => $products->count() === 15 && $products->total() === 18)
        ->call('setPage', 2)->assertViewHas('products', fn ($products) => $products->currentPage() === 2);
    foreach (['COFFEE-001', '0012345678901', 'Coffee beans'] as $search) {
        $component->set('search', $search)
            ->assertViewHas('products', fn ($products) => $products->currentPage() === 1 && $products->total() === 1 && $products->first()->id === $target->id);
    }
    $component->set('search', 'Coffee')->set('category', (string) $target->category_id)
        ->set('brand', (string) $target->brand_id)->set('status', 'active')
        ->assertViewHas('products', fn ($products) => $products->total() === 1)
        ->set('status', 'inactive')->assertSee('No products match your filters.')
        ->call('clearFilters')->assertSet('search', '')->assertSet('category', '')->assertSet('brand', '')->assertSet('status', '')
        ->assertViewHas('products', fn ($products) => $products->total() === 18);
    $this->manager->roles()->detach();
    $component->set('search', 'Coffee')->assertForbidden();
});

test('product content is escaped and invalid filters do not expose other records', function () {
    Product::factory()->withStockItem()->create(['name' => '<script>alert(1)</script>']);
    Livewire::actingAs($this->manager)->test(ProductTable::class)->assertDontSeeHtml('<script>alert(1)</script>')
        ->set('category', 'invalid')->assertSee('No products match your filters.');
});

test('variant products cannot be changed by the simple product module', function () {
    $product = Product::factory()->create(['has_variants' => true]);
    $this->actingAs($this->manager)->get(route('products.edit', $product))->assertForbidden();
    $this->put(route('products.update', $product), $this->data)->assertForbidden();
    $this->patch(route('products.status', $product), ['status' => 'inactive'])->assertForbidden();
    expect(fn () => app(ProductService::class)->save($this->data, $product))->toThrow(ValidationException::class);
    expect(fn () => app(ProductService::class)->changeStatus($product, ProductStatus::Inactive))->toThrow(ValidationException::class);
});

test('status validation and legacy products without stock items are handled safely', function () {
    $product = Product::factory()->create();
    $this->actingAs($this->manager)->patch(route('products.status', $product), ['status' => 'invalid'])->assertSessionHasErrors('status');
    $this->patch(route('products.status', $product), ['status' => 'inactive'])->assertSessionHasErrors('product');
    $this->put(route('products.update', $product), $this->data)->assertSessionHasNoErrors();
    expect($product->fresh()->stockItem->sku)->toBe($this->data['sku']);
});

test('product permission seeding and grants are idempotent and do not create users', function () {
    $this->seed(ProductPermissionSeeder::class);
    expect(Permission::count())->toBe(3);
    $this->artisan('products:grant', ['email' => $this->manager->email])->assertSuccessful();
    $this->artisan('products:grant', ['email' => $this->manager->email])->assertSuccessful();
    expect($this->manager->roles()->count())->toBe(1);
    $this->artisan('products:grant', ['email' => 'missing@example.com'])->assertFailed();
    $this->manager->update(['is_active' => false]);
    $this->artisan('products:grant', ['email' => $this->manager->email])->assertFailed();
    expect(User::count())->toBe(1);
});
