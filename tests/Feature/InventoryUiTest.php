<?php

use App\InventoryMovementType;
use App\Livewire\InventoryMovementTable;
use App\Livewire\InventoryTable;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Permission;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Database\Seeders\InventoryPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(InventoryPermissionSeeder::class);
    $this->viewer = User::factory()->create(['is_active' => true]);
    $this->viewer->roles()->attach(Role::where('slug', 'inventory-viewer')->firstOrFail());
    $this->item = StockItem::factory()->create(['sku' => 'CORE-001', 'barcode' => '0009876']);
    $this->warehouse = Warehouse::factory()->create();
});

test('inventory pages require active users with appropriate permissions', function () {
    foreach (['inventory.index', 'inventory.dashboard', 'inventory.show', 'inventory.movements'] as $name) {
        $url = route($name, $name === 'inventory.show' ? $this->item : []);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['is_active' => true]))->get($url)->assertForbidden();
        auth()->forgetGuards();
    }
    $this->actingAs($this->viewer)->get(route('inventory.index'))->assertOk()->assertSeeLivewire(InventoryTable::class);
    $this->get(route('inventory.dashboard'))->assertOk();
    $this->get(route('inventory.show', $this->item))->assertOk()->assertSee('CORE-001')->assertSeeLivewire(InventoryMovementTable::class);
    $this->get(route('inventory.movements'))->assertOk();
    $this->viewer->update(['is_active' => false]);
    $this->get(route('inventory.index'))->assertForbidden();
    Livewire::actingAs($this->viewer)->test(InventoryTable::class)->assertForbidden();
    Livewire::actingAs($this->viewer)->test(InventoryMovementTable::class)->assertForbidden();
});

test('movement permission is enforced independently on pages dashboard and components', function () {
    $role = Role::create(['slug' => 'balance-only', 'name' => 'Balance only']);
    $role->permissions()->attach(Permission::where('slug', 'inventory.view')->firstOrFail());
    $this->viewer->roles()->sync([$role->id]);
    $this->actingAs($this->viewer)->get(route('inventory.dashboard'))->assertOk()->assertDontSee('Recent movements');
    $this->get(route('inventory.show', $this->item))->assertOk()->assertDontSeeLivewire(InventoryMovementTable::class);
    $this->get(route('inventory.movements'))->assertForbidden();
    Livewire::actingAs($this->viewer)->test(InventoryMovementTable::class)->assertForbidden();
});

test('zero balances appear and are searchable without creating inventories', function () {
    $this->item->product->update(['name' => 'Never received']);
    Livewire::actingAs($this->viewer)->test(InventoryTable::class)
        ->assertSee('0.0000')->assertSee('Out of stock')
        ->set('search', 'Never received')->assertViewHas('inventories', fn ($rows) => $rows->total() === 1)
        ->set('category', (string) $this->item->product->category_id)->set('lowStock', true)
        ->assertViewHas('inventories', fn ($rows) => $rows->total() === 1);
    $this->assertDatabaseCount('inventories', 0);
});

test('overview combines search warehouse category and low-stock boundary with overrides', function () {
    $otherWarehouse = Warehouse::factory()->create();
    $otherItem = StockItem::factory()->create();
    Inventory::factory()->create(['stock_item_id' => $this->item->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => '5', 'reserved_quantity' => '1']);
    Inventory::factory()->create(['stock_item_id' => $this->item->id, 'warehouse_id' => $otherWarehouse->id, 'quantity' => '6']);
    $component = Livewire::actingAs($this->viewer)->test(InventoryTable::class)
        ->set('search', '0009876')->set('category', (string) $this->item->product->category_id)
        ->set('lowStock', true)->assertViewHas('inventories', fn ($rows) => $rows->total() === 1)
        ->assertSee('4.0000')->set('warehouse', (string) $otherWarehouse->id)->assertSee('No inventory matches your filters.')
        ->set('warehouse', (string) $this->warehouse->id)->assertSee('Low stock');
    Inventory::where('stock_item_id', $this->item->id)->where('warehouse_id', $this->warehouse->id)->update(['reorder_level' => '0']);
    $component->set('search', 'CORE-001')->assertSee('No inventory matches your filters.')
        ->call('clearFilters')->assertViewHas('inventories', fn ($rows) => $rows->total() === 4);
});

test('variant inventory uses its parent category and independent item identity', function () {
    $category = Category::factory()->create();
    $variant = ProductVariant::factory()->create(['name' => 'Large blue']);
    $variant->product->update(['category_id' => $category->id, 'name' => 'Variant parent']);
    $item = StockItem::factory()->create(['product_id' => null, 'product_variant_id' => $variant->id]);
    Livewire::actingAs($this->viewer)->test(InventoryTable::class)
        ->set('category', (string) $category->id)->set('search', 'Large blue')
        ->assertViewHas('inventories', fn ($rows) => $rows->total() === 1 && $rows->first()->stock_item_id === $item->id)
        ->assertSee('Variant parent');
});

test('overview pagination resets and component scope cannot be modified', function () {
    StockItem::factory()->count(16)->create();
    $component = Livewire::actingAs($this->viewer)->test(InventoryTable::class)
        ->assertViewHas('inventories', fn ($rows) => $rows->count() === 15 && $rows->total() === 17)
        ->call('setPage', 2, 'stockPage')->assertViewHas('inventories', fn ($rows) => $rows->currentPage() === 2)
        ->set('search', 'CORE-001')->assertViewHas('inventories', fn ($rows) => $rows->currentPage() === 1 && $rows->total() === 1);
    expect(fn () => $component->set('stockItemId', 999))->toThrow(CannotUpdateLockedPropertyException::class);
    $this->viewer->roles()->detach();
    Livewire::actingAs($this->viewer)->test(InventoryTable::class)->assertForbidden();
});

test('movement history shows ledger data filters correctly and resets pagination', function () {
    $other = Warehouse::factory()->create();
    InventoryMovement::factory()->count(16)->create(['stock_item_id' => $this->item->id, 'warehouse_id' => $other->id, 'performed_by' => $this->viewer->id]);
    app(InventoryService::class)->increaseStock($this->item, $this->warehouse, '2.5', InventoryMovementType::SaleReturn, $this->viewer, 'sale_return', 10, 'Returned unopened');
    $component = Livewire::actingAs($this->viewer)->test(InventoryMovementTable::class)
        ->assertViewHas('movements', fn ($rows) => $rows->total() === 17)
        ->call('setPage', 2, 'movementPage')->assertViewHas('movements', fn ($rows) => $rows->currentPage() === 2)
        ->set('warehouse', (string) $this->warehouse->id)->set('type', 'sale_return')->set('search', 'CORE-001')
        ->assertViewHas('movements', fn ($rows) => $rows->currentPage() === 1 && $rows->total() === 1)
        ->assertSee('Returned unopened')->assertSee('sale_return #10')->assertSee($this->viewer->name);
    expect(fn () => $component->set('stockItemId', 999))->toThrow(CannotUpdateLockedPropertyException::class);
    $this->viewer->roles()->detach();
    $component = Livewire::actingAs($this->viewer)->test(InventoryMovementTable::class)->assertForbidden();
});

test('inventory dashboard summarizes pairs and low stock without summing unlike units', function () {
    Warehouse::factory()->create();
    $this->actingAs($this->viewer)->get(route('inventory.dashboard'))->assertOk()
        ->assertViewHas('pairs', 2)->assertViewHas('lowStock', 2)->assertViewHas('outOfStock', 2);
    $this->assertDatabaseCount('inventories', 0);
});

test('malicious content is escaped invalid filters return no records and no stock write endpoints exist', function () {
    $this->item->product->update(['name' => '<script>alert(1)</script>']);
    Livewire::actingAs($this->viewer)->test(InventoryTable::class)->assertDontSeeHtml('<script>alert(1)</script>')
        ->set('category', 'invalid')->assertSee('No inventory matches your filters.');
    $this->actingAs($this->viewer)->post(route('inventory.index'), ['quantity' => 999])->assertStatus(405);
    $this->put(route('inventory.show', $this->item), ['quantity' => 999])->assertStatus(405);
});

test('inventory access seeding and grants are idempotent', function () {
    $this->seed(InventoryPermissionSeeder::class);
    expect(Permission::count())->toBe(2);
    $this->artisan('inventory:grant', ['email' => $this->viewer->email])->assertSuccessful();
    $this->artisan('inventory:grant', ['email' => $this->viewer->email])->assertSuccessful();
    expect($this->viewer->roles()->count())->toBe(1);
    $this->artisan('inventory:grant', ['email' => 'missing@example.test'])->assertFailed();
});
