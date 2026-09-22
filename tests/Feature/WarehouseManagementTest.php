<?php

use App\Livewire\WarehouseTable;
use App\Models\Inventory;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\WarehouseService;
use Database\Seeders\WarehousePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(WarehousePermissionSeeder::class);
    $this->manager = User::factory()->create(['is_active' => true]);
    $this->manager->roles()->attach(Role::where('slug', 'warehouse-manager')->firstOrFail());
    $this->data = ['code' => 'MAIN', 'name' => 'Main warehouse', 'address' => 'Main Street', 'status' => 'active'];
});

test('managers can create multiple warehouses view edit and change status', function () {
    $this->actingAs($this->manager);
    $this->get(route('warehouses.index'))->assertOk()->assertSeeLivewire(WarehouseTable::class);
    $this->get(route('warehouses.create'))->assertOk();
    foreach (['MAIN', 'BR001', 'BR002'] as $code) {
        $this->post(route('warehouses.store'), [...$this->data, 'code' => $code, 'name' => $code.' warehouse', 'quantity' => 100])
            ->assertSessionHasNoErrors();
    }
    $warehouse = Warehouse::where('code', 'MAIN')->firstOrFail();
    $this->get(route('warehouses.show', $warehouse))->assertOk()->assertSee('MAIN');
    $this->get(route('warehouses.edit', $warehouse))->assertOk();
    $this->put(route('warehouses.update', $warehouse), [...$this->data, 'name' => 'Renamed warehouse'])
        ->assertSessionHasNoErrors()->assertRedirect(route('warehouses.show', $warehouse));
    expect($warehouse->fresh()->name)->toBe('Renamed warehouse');
    foreach (['inactive', 'active'] as $status) {
        $this->patch(route('warehouses.status', $warehouse), ['status' => $status, 'name' => 'Ignored', 'is_active' => false])
            ->assertSessionHasNoErrors();
        expect($warehouse->fresh()->status)->toBe($status)
            ->and($warehouse->fresh()->is_active)->toBe($status === 'active')
            ->and($warehouse->fresh()->name)->toBe('Renamed warehouse');
    }
    $this->assertDatabaseCount('warehouses', 3);
    $this->assertDatabaseCount('inventories', 0);
    $this->assertDatabaseCount('inventory_movements', 0);
    $this->assertDatabaseCount('stock_transfers', 0);
    expect(Schema::hasColumn('warehouses', 'quantity'))->toBeFalse();
    $this->delete(route('warehouses.show', $warehouse))->assertStatus(405);
});

test('guests and unauthorized users cannot access warehouse actions', function () {
    $warehouse = Warehouse::factory()->create();
    $this->get(route('warehouses.index'))->assertRedirect(route('login'));
    $this->post(route('warehouses.store'), $this->data)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['is_active' => true]));
    foreach (['index', 'create', 'show', 'edit'] as $action) {
        $this->get(route('warehouses.'.$action, $warehouse))->assertForbidden();
    }
    $this->post(route('warehouses.store'), $this->data)->assertForbidden();
    $this->put(route('warehouses.update', $warehouse), $this->data)->assertForbidden();
    $this->patch(route('warehouses.status', $warehouse), ['status' => 'inactive'])->assertForbidden();
    Livewire::test(WarehouseTable::class)->assertForbidden();
});

test('view-only and inactive users cannot modify warehouses', function () {
    $role = Role::create(['name' => 'Warehouse Viewer', 'slug' => 'warehouse-viewer']);
    $role->permissions()->attach(Permission::where('slug', 'warehouses.view')->firstOrFail());
    $this->manager->roles()->sync([$role->id]);
    $warehouse = Warehouse::factory()->create();
    $this->actingAs($this->manager)->get(route('warehouses.index'))->assertOk()->assertDontSee('Add warehouse');
    $this->get(route('warehouses.show', $warehouse))->assertOk()->assertDontSee('Edit warehouse');
    $this->post(route('warehouses.store'), $this->data)->assertForbidden();
    $this->put(route('warehouses.update', $warehouse), $this->data)->assertForbidden();
    $this->patch(route('warehouses.status', $warehouse), ['status' => 'inactive'])->assertForbidden();
    $this->manager->update(['is_active' => false]);
    $this->get(route('warehouses.index'))->assertForbidden();
    Livewire::actingAs($this->manager)->test(WarehouseTable::class)->assertForbidden();
});

test('warehouse validation rejects missing invalid oversized and duplicate fields', function () {
    $warehouse = Warehouse::factory()->create($this->data);
    $this->actingAs($this->manager)->post(route('warehouses.store'), ['code' => ' ', 'name' => ' ', 'status' => 'invalid'])
        ->assertSessionHasErrors(['code', 'name', 'status']);
    $this->post(route('warehouses.store'), $this->data)->assertSessionHasErrors(['code', 'name']);
    $this->post(route('warehouses.store'), [...$this->data, 'code' => str_repeat('x', 256), 'name' => str_repeat('n', 256), 'address' => str_repeat('a', 256)])
        ->assertSessionHasErrors(['code', 'name', 'address']);
    $this->put(route('warehouses.update', $warehouse), $this->data)->assertSessionHasNoErrors();
    $other = Warehouse::factory()->create();
    $this->put(route('warehouses.update', $other), $this->data)->assertSessionHasErrors(['code', 'name']);
    $this->patch(route('warehouses.status', $warehouse), ['status' => 'invalid'])->assertSessionHasErrors('status');
    $this->assertDatabaseCount('warehouses', 2);
});

test('warehouse inputs are trimmed address is optional and legacy flags cannot override requests', function () {
    $this->actingAs($this->manager)->post(route('warehouses.store'), [
        'code' => ' BR001 ', 'name' => ' Branch one ', 'address' => ' ', 'status' => 'inactive', 'is_active' => true,
    ])->assertSessionHasNoErrors();
    $warehouse = Warehouse::firstOrFail();
    expect($warehouse->code)->toBe('BR001')->and($warehouse->name)->toBe('Branch one')
        ->and($warehouse->address)->toBeNull()->and($warehouse->is_active)->toBeFalse();
});

test('warehouse updates and deactivation preserve linked balances and do not create movements', function () {
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->withStockItem()->create();
    $inventory = Inventory::factory()->create([
        'warehouse_id' => $warehouse->id, 'stock_item_id' => $product->stockItem->id,
        'quantity' => '12.5000', 'reserved_quantity' => '2.0000',
    ]);
    $this->actingAs($this->manager)->patch(route('warehouses.status', $warehouse), ['status' => 'inactive'])->assertSessionHasNoErrors();
    expect($warehouse->inventories()->sole()->id)->toBe($inventory->id);
    expect($inventory->fresh()->quantity)->toBe('12.5000')->and($inventory->fresh()->reserved_quantity)->toBe('2.0000');
    $this->assertDatabaseCount('inventory_movements', 0);
});

test('live warehouse search supports code name pagination escaping and authorization refresh', function () {
    Warehouse::factory()->count(12)->create();
    $target = Warehouse::factory()->create(['name' => 'Special branch', 'code' => 'BR002']);
    $component = Livewire::actingAs($this->manager)->test(WarehouseTable::class)
        ->assertViewHas('warehouses', fn ($warehouses) => $warehouses->total() === 13 && $warehouses->count() === 10)
        ->call('setPage', 2)->assertViewHas('warehouses', fn ($warehouses) => $warehouses->currentPage() === 2);
    foreach (['Special branch', 'BR002'] as $search) {
        $component->set('search', $search)
            ->assertViewHas('warehouses', fn ($warehouses) => $warehouses->currentPage() === 1 && $warehouses->total() === 1 && $warehouses->first()->id === $target->id);
    }
    $component->set('search', 'does-not-exist')->assertSee('No warehouses match your search.');
    $target->update(['name' => '<script>alert(1)</script>']);
    $component->set('search', 'alert')->assertDontSeeHtml('<script>alert(1)</script>');
    $this->manager->roles()->detach();
    $component->set('search', 'BR002')->assertForbidden();
});

test('warehouse status stays compatible with legacy active flags', function () {
    $warehouse = Warehouse::factory()->inactive()->create();
    expect($warehouse->is_active)->toBeFalse();
    $warehouse->update(['is_active' => true]);
    expect($warehouse->fresh()->status)->toBe('active');
    $warehouse->update(['status' => 'inactive']);
    expect($warehouse->fresh()->is_active)->toBeFalse();
    $legacy = Warehouse::create(['code' => 'LEGACY', 'name' => 'Legacy', 'is_active' => false]);
    expect($legacy->status)->toBe('inactive');
});

test('warehouse migration preserves legacy identities addresses and inactive status', function () {
    $migration = require database_path('migrations/2026_09_19_032528_add_status_to_warehouses_table.php');
    $migration->down();
    $id = DB::table('warehouses')->insertGetId(['code' => 'OLD', 'name' => 'Existing warehouse', 'address' => 'Old address', 'is_active' => false]);
    $migration->up();
    $warehouse = Warehouse::findOrFail($id);
    expect($warehouse->status)->toBe('inactive')->and($warehouse->code)->toBe('OLD')->and($warehouse->address)->toBe('Old address');
});

test('database uniqueness races return validation errors without modifying existing warehouses', function () {
    $warehouse = Warehouse::factory()->create($this->data);
    expect(fn () => app(WarehouseService::class)->save($this->data))->toThrow(ValidationException::class);
    expect($warehouse->fresh()->name)->toBe($this->data['name']);
    $this->assertDatabaseCount('warehouses', 1);
});

test('warehouse permission seeding and grants are idempotent and never create users', function () {
    $this->seed(WarehousePermissionSeeder::class);
    expect(Permission::count())->toBe(3);
    $this->artisan('warehouses:grant', ['email' => $this->manager->email])->assertSuccessful();
    $this->artisan('warehouses:grant', ['email' => $this->manager->email])->assertSuccessful();
    expect($this->manager->roles()->count())->toBe(1);
    $this->artisan('warehouses:grant', ['email' => 'missing@example.test'])->assertFailed();
    $this->manager->update(['is_active' => false]);
    $this->artisan('warehouses:grant', ['email' => $this->manager->email])->assertFailed();
    expect(User::count())->toBe(1);
});
