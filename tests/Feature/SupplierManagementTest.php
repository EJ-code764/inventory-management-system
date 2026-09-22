<?php

use App\Livewire\SupplierTable;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SupplierService;
use Database\Seeders\SupplierPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(SupplierPermissionSeeder::class);
    $this->manager = User::factory()->create(['is_active' => true]);
    $this->manager->roles()->attach(Role::where('slug', 'supplier-manager')->firstOrFail());
    $this->data = [
        'supplier_code' => 'SUP-001', 'name' => 'Example Supplier', 'contact_person' => 'Alex Sample',
        'phone' => '+63 (2) 8123-4567', 'email' => 'contact@example.test', 'address' => '123 Main Street', 'status' => 'active',
    ];
});

test('managers can create view edit activate deactivate and delete unreferenced suppliers', function () {
    $this->actingAs($this->manager);
    $this->get(route('suppliers.index'))->assertOk()->assertSeeLivewire(SupplierTable::class);
    $this->get(route('suppliers.create'))->assertOk();
    $this->post(route('suppliers.store'), $this->data)->assertSessionHasNoErrors();
    $supplier = Supplier::sole();
    $this->get(route('suppliers.show', $supplier))->assertOk()->assertSee('SUP-001')->assertSee('Alex Sample')->assertSee('Confirm deletion');
    $this->get(route('suppliers.edit', $supplier))->assertOk();
    $this->put(route('suppliers.update', $supplier), [...$this->data, 'name' => 'Updated Supplier', 'contact_person' => 'New Contact'])
        ->assertSessionHasNoErrors()->assertRedirect(route('suppliers.show', $supplier));
    expect($supplier->fresh()->name)->toBe('Updated Supplier')->and($supplier->fresh()->contact_person)->toBe('New Contact');
    foreach (['inactive', 'active'] as $status) {
        $this->patch(route('suppliers.status', $supplier), ['status' => $status, 'name' => 'Ignored', 'is_active' => false])->assertSessionHasNoErrors();
        expect($supplier->fresh()->status)->toBe($status)->and($supplier->fresh()->is_active)->toBe($status === 'active')
            ->and($supplier->fresh()->name)->toBe('Updated Supplier');
    }
    $this->delete(route('suppliers.destroy', $supplier))->assertSessionHasNoErrors()->assertRedirect(route('suppliers.index'));
    $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    $this->assertDatabaseCount('purchase_orders', 0);
    $this->assertDatabaseCount('inventories', 0);
    $this->assertDatabaseCount('inventory_movements', 0);
});

test('guests and unauthorized users cannot access supplier routes or components', function () {
    $supplier = Supplier::factory()->create();
    $this->get(route('suppliers.index'))->assertRedirect(route('login'));
    $this->post(route('suppliers.store'), $this->data)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['is_active' => true]));
    foreach (['index', 'create', 'show', 'edit'] as $action) {
        $this->get(route('suppliers.'.$action, $supplier))->assertForbidden();
    }
    $this->post(route('suppliers.store'), $this->data)->assertForbidden();
    $this->put(route('suppliers.update', $supplier), $this->data)->assertForbidden();
    $this->patch(route('suppliers.status', $supplier), ['status' => 'inactive'])->assertForbidden();
    $this->delete(route('suppliers.destroy', $supplier))->assertForbidden();
    Livewire::test(SupplierTable::class)->assertForbidden();
    $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
});

test('view-only permissions do not allow mutation and inactive users are rejected', function () {
    $role = Role::create(['name' => 'Supplier Viewer', 'slug' => 'supplier-viewer']);
    $role->permissions()->attach(Permission::where('slug', 'suppliers.view')->firstOrFail());
    $this->manager->roles()->sync([$role->id]);
    $supplier = Supplier::factory()->create();
    $this->actingAs($this->manager)->get(route('suppliers.index'))->assertOk()->assertDontSee('Add supplier');
    $this->get(route('suppliers.show', $supplier))->assertOk()->assertDontSee('Edit supplier')->assertDontSee('Delete supplier');
    $this->post(route('suppliers.store'), $this->data)->assertForbidden();
    $this->put(route('suppliers.update', $supplier), $this->data)->assertForbidden();
    $this->patch(route('suppliers.status', $supplier), ['status' => 'inactive'])->assertForbidden();
    $this->delete(route('suppliers.destroy', $supplier))->assertForbidden();
    $this->manager->update(['is_active' => false]);
    $this->get(route('suppliers.index'))->assertForbidden();
    Livewire::actingAs($this->manager)->test(SupplierTable::class)->assertForbidden();
});

test('update permission alone does not allow supplier deletion', function () {
    $role = Role::create(['name' => 'Supplier Editor', 'slug' => 'supplier-editor']);
    $role->permissions()->attach(Permission::whereIn('slug', ['suppliers.view', 'suppliers.update'])->pluck('id'));
    $this->manager->roles()->sync([$role->id]);
    $supplier = Supplier::factory()->create();
    $this->actingAs($this->manager)->get(route('suppliers.show', $supplier))->assertOk()->assertSee('Edit supplier')->assertDontSee('Delete supplier');
    $this->delete(route('suppliers.destroy', $supplier))->assertForbidden();
});

test('supplier validation enforces required fields unique identities and contact formats', function () {
    $this->actingAs($this->manager)->post(route('suppliers.store'), [
        'supplier_code' => ' ', 'name' => ' ', 'email' => 'invalid', 'phone' => 'not a phone', 'status' => 'bad',
    ])->assertSessionHasErrors(['supplier_code', 'name', 'email', 'phone', 'status']);
    $existing = Supplier::factory()->create($this->data);
    $this->post(route('suppliers.store'), $this->data)->assertSessionHasErrors(['supplier_code', 'name']);
    $this->put(route('suppliers.update', $existing), $this->data)->assertSessionHasNoErrors();
    $other = Supplier::factory()->create();
    $this->put(route('suppliers.update', $other), $this->data)->assertSessionHasErrors(['supplier_code', 'name']);
    $this->patch(route('suppliers.status', $existing), ['status' => 'invalid'])->assertSessionHasErrors('status');
});

test('supplier field length limits are validated', function (string $field, int $limit) {
    $this->actingAs($this->manager)->post(route('suppliers.store'), [...$this->data, $field => str_repeat('a', $limit + 1)])
        ->assertSessionHasErrors($field);
    $this->assertDatabaseCount('suppliers', 0);
})->with([['supplier_code', 64], ['name', 255], ['contact_person', 255], ['phone', 50], ['email', 255], ['address', 255]]);

test('optional contact fields accept null and input is trimmed without accepting legacy flags', function () {
    $this->actingAs($this->manager)->post(route('suppliers.store'), [
        'supplier_code' => ' SUP-002 ', 'name' => ' Trimmed Supplier ', 'contact_person' => ' ', 'phone' => '', 'email' => '', 'address' => '',
        'status' => 'inactive', 'is_active' => true, 'contact_name' => 'Injected',
    ])->assertSessionHasNoErrors();
    $supplier = Supplier::sole();
    expect($supplier->supplier_code)->toBe('SUP-002')->and($supplier->name)->toBe('Trimmed Supplier')
        ->and($supplier->contact_person)->toBeNull()->and($supplier->phone)->toBeNull()
        ->and($supplier->email)->toBeNull()->and($supplier->address)->toBeNull()->and($supplier->is_active)->toBeFalse();
});

test('referenced suppliers cannot be deleted but can be deactivated without altering orders', function () {
    $supplier = Supplier::factory()->create();
    $order = PurchaseOrder::factory()->create([
        'number' => 'PO-REFERENCE-FIXTURE', 'supplier_id' => $supplier->id,
        'warehouse_id' => Warehouse::factory()->create()->id, 'created_by' => $this->manager->id,
    ]);
    expect($supplier->purchaseOrders()->sole()->id)->toBe($order->id);
    $this->actingAs($this->manager)->from(route('suppliers.show', $supplier))
        ->delete(route('suppliers.destroy', $supplier))->assertSessionHasErrors('supplier');
    $this->patch(route('suppliers.status', $supplier), ['status' => 'inactive'])->assertSessionHasNoErrors();
    expect($supplier->fresh()->status)->toBe('inactive')->and($order->fresh()->supplier_id)->toBe($supplier->id);
    $this->assertDatabaseCount('purchase_orders', 1);
});

test('search matches contact details with status filtering and resets pagination', function () {
    Supplier::factory()->count(12)->create();
    $supplier = Supplier::factory()->inactive()->create([
        'supplier_code' => 'FIND-CODE', 'name' => 'Findable Supplier', 'contact_person' => 'Findable Contact',
        'phone' => '09123456789', 'email' => 'findable@example.test',
    ]);
    $component = Livewire::actingAs($this->manager)->test(SupplierTable::class)
        ->assertViewHas('suppliers', fn ($rows) => $rows->total() === 13 && $rows->count() === 10)
        ->call('setPage', 2)->assertViewHas('suppliers', fn ($rows) => $rows->currentPage() === 2);
    foreach (['FIND-CODE', 'Findable Supplier', 'Findable Contact', '09123456789', 'findable@example.test'] as $search) {
        $component->set('search', $search)
            ->assertViewHas('suppliers', fn ($rows) => $rows->currentPage() === 1 && $rows->total() === 1 && $rows->first()->id === $supplier->id);
    }
    $component->set('status', 'active')->assertSee('No suppliers match your filters.')
        ->set('status', 'inactive')->assertViewHas('suppliers', fn ($rows) => $rows->total() === 1)
        ->call('clearFilters')->assertSet('search', '')->assertSet('status', '')
        ->assertViewHas('suppliers', fn ($rows) => $rows->total() === 13)
        ->set('status', 'invalid')->assertSee('No suppliers match your filters.');
});

test('supplier output is escaped and livewire rechecks revoked permissions', function () {
    Supplier::factory()->create(['name' => '<script>alert(1)</script>']);
    $component = Livewire::actingAs($this->manager)->test(SupplierTable::class)->assertDontSeeHtml('<script>alert(1)</script>');
    $this->manager->roles()->detach();
    $component->set('search', 'anything')->assertForbidden();
});

test('supplier code has a database uniqueness constraint and race conflicts are validation errors', function () {
    Supplier::factory()->create($this->data);
    expect(fn () => app(SupplierService::class)->save([...$this->data, 'name' => 'Different name']))->toThrow(ValidationException::class);
    expect(fn () => Supplier::factory()->create(['supplier_code' => $this->data['supplier_code']]))->toThrow(QueryException::class);
    $this->assertDatabaseCount('suppliers', 1);
});

test('supplier migration preserves legacy contacts statuses and generates unique non-null codes', function () {
    $migration = require database_path('migrations/2026_09_19_044148_extend_suppliers_for_management.php');
    $migration->down();
    $first = DB::table('suppliers')->insertGetId(['name' => 'Legacy one', 'contact_name' => 'Old contact', 'phone' => '12345', 'email' => 'old@example.test', 'address' => 'Old address', 'is_active' => false]);
    $second = DB::table('suppliers')->insertGetId(['name' => 'Legacy two', 'is_active' => true]);
    $migration->up();
    $supplier = Supplier::findOrFail($first);
    expect($supplier->contact_person)->toBe('Old contact')->and($supplier->status)->toBe('inactive')
        ->and($supplier->supplier_code)->toBe('SUP-'.str_pad((string) $first, 6, '0', STR_PAD_LEFT))
        ->and($supplier->phone)->toBe('12345')->and($supplier->email)->toBe('old@example.test')->and($supplier->address)->toBe('Old address');
    expect(Supplier::findOrFail($second)->supplier_code)->not->toBe($supplier->supplier_code);
    $migration->down();
    expect(DB::table('suppliers')->where('id', $first)->value('contact_name'))->toBe('Old contact');
    $migration->up();
    expect(Supplier::findOrFail($first)->status)->toBe('inactive');
});

test('supplier permission seeding and grants are idempotent and do not create users', function () {
    $this->seed(SupplierPermissionSeeder::class);
    expect(Permission::count())->toBe(4);
    $this->artisan('suppliers:grant', ['email' => $this->manager->email])->assertSuccessful();
    $this->artisan('suppliers:grant', ['email' => $this->manager->email])->assertSuccessful();
    expect($this->manager->roles()->count())->toBe(1);
    $this->artisan('suppliers:grant', ['email' => 'missing@example.test'])->assertFailed();
    $this->manager->update(['is_active' => false]);
    $this->artisan('suppliers:grant', ['email' => $this->manager->email])->assertFailed();
    expect(User::count())->toBe(1);
});
