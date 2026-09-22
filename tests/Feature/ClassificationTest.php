<?php

use App\Livewire\ClassificationTable;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\ClassificationPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

dataset('classifications', [
    ['categories', Category::class],
    ['brands', Brand::class],
    ['units', Unit::class],
]);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(ClassificationPermissionSeeder::class);
    $this->manager = User::factory()->create(['is_active' => true]);
    $this->manager->roles()->attach(Role::where('slug', 'classification-manager')->firstOrFail());
});

test('guests and users without permission cannot access classifications', function (string $resource, string $model) {
    $record = $model::factory()->create();
    $this->get(route($resource.'.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['is_active' => true]));
    $this->get(route($resource.'.index'))->assertForbidden();
    $this->get(route($resource.'.create'))->assertForbidden();
    $this->get(route($resource.'.show', ['record' => $record->id]))->assertForbidden();
    $this->get(route($resource.'.edit', ['record' => $record->id]))->assertForbidden();
    $this->post(route($resource.'.store'), ['name' => 'Denied', 'status' => 'active'])->assertForbidden();
    $this->put(route($resource.'.update', ['record' => $record->id]), ['name' => 'Denied', 'status' => 'active'])->assertForbidden();
    $this->patch(route($resource.'.status', ['record' => $record->id]), ['status' => 'inactive'])->assertForbidden();
    $this->delete(route($resource.'.destroy', ['record' => $record->id]))->assertForbidden();
    expect($record->fresh()->status)->toBe('active');
})->with('classifications');

test('managers can create read update change status and delete unreferenced records', function (string $resource, string $model) {
    $this->actingAs($this->manager);
    $this->get(route($resource.'.index'))->assertOk()->assertSeeLivewire(ClassificationTable::class);
    $this->get(route($resource.'.create'))->assertOk();
    $data = ['name' => '  Example classification  ', 'status' => 'active', 'description' => 'Useful description', 'short_name' => 'pc'];
    $this->post(route($resource.'.store'), $data)->assertSessionHasNoErrors()->assertRedirect(route($resource.'.index'));
    $record = $model::where('name', 'Example classification')->firstOrFail();
    $this->get(route($resource.'.show', ['record' => $record->id]))->assertOk()->assertSee('Example classification');
    $this->get(route($resource.'.edit', ['record' => $record->id]))->assertOk();
    $data['name'] = 'Updated classification';
    $this->put(route($resource.'.update', ['record' => $record->id]), $data)->assertSessionHasNoErrors();
    expect($record->fresh()->name)->toBe('Updated classification');
    foreach (['inactive', 'active'] as $status) {
        $this->patch(route($resource.'.status', ['record' => $record->id]), ['status' => $status])->assertSessionHasNoErrors();
        expect($record->fresh()->status)->toBe($status);
        expect((bool) $record->fresh()->is_active)->toBe($status === 'active');
    }
    $this->delete(route($resource.'.destroy', ['record' => $record->id]))->assertSessionHasNoErrors()->assertRedirect(route($resource.'.index'));
    $this->assertDatabaseMissing($resource, ['id' => $record->id]);
})->with('classifications');

test('validation rejects invalid and duplicate values but permits an unchanged name', function (string $resource, string $model) {
    $record = $model::factory()->create(['name' => 'Existing']);
    $this->actingAs($this->manager);
    $this->post(route($resource.'.store'), ['name' => ' ', 'status' => 'invalid'])->assertSessionHasErrors(['name', 'status']);
    $data = ['name' => 'Existing', 'status' => 'active', 'short_name' => 'pc'];
    $this->post(route($resource.'.store'), $data)->assertSessionHasErrors('name');
    $this->put(route($resource.'.update', ['record' => $record->id]), $data)->assertSessionHasNoErrors();
    $other = $model::factory()->create();
    $this->put(route($resource.'.update', ['record' => $other->id]), $data)->assertSessionHasErrors('name');
    $this->patch(route($resource.'.status', ['record' => $record->id]), ['status' => 'invalid'])->assertSessionHasErrors('status');
    $data['name'] = str_repeat('a', 256);
    $this->post(route($resource.'.store'), $data)->assertSessionHasErrors('name');
    if ($resource === 'units') {
        $this->post(route($resource.'.store'), ['name' => 'New', 'status' => 'active'])->assertSessionHasErrors('short_name');
    } else {
        $this->post(route($resource.'.store'), ['name' => 'New', 'status' => 'active', 'description' => str_repeat('x', 5001)])->assertSessionHasErrors('description');
    }
})->with('classifications');

test('referenced records cannot be deleted but can be deactivated', function (string $resource, string $model) {
    $category = Category::factory()->create();
    $brand = Brand::factory()->create();
    $unit = Unit::factory()->create();
    $product = Product::factory()->create(['name' => 'Existing product fixture', 'category_id' => $category->id, 'brand_id' => $brand->id, 'unit_id' => $unit->id]);
    $record = match ($resource) {
        'categories' => $category, 'brands' => $brand, 'units' => $unit
    };
    $this->actingAs($this->manager)->from(route($resource.'.index'))
        ->delete(route($resource.'.destroy', ['record' => $record->id]))->assertSessionHasErrors('record');
    expect($record->fresh())->not->toBeNull();
    $this->patch(route($resource.'.status', ['record' => $record->id]), ['status' => 'inactive'])->assertSessionHasNoErrors();
    expect($record->fresh()->status)->toBe('inactive');
    expect($product->fresh()->brand_id)->toBe($brand->id);
})->with('classifications');

test('live search resets pagination and escapes names', function (string $resource, string $model) {
    $model::factory()->count(12)->create();
    $model::factory()->create(['name' => '<script>alert("example")</script>']);
    $component = Livewire::actingAs($this->manager)->test(ClassificationTable::class, ['resource' => $resource])
        ->assertViewHas('records', fn ($records) => $records->count() === 10 && $records->total() === 13)
        ->call('setPage', 2)->assertViewHas('records', fn ($records) => $records->currentPage() === 2)
        ->set('search', 'alert')->assertViewHas('records', fn ($records) => $records->currentPage() === 1 && $records->total() === 1)
        ->assertDontSeeHtml('<script>alert("example")</script>');
    $component->set('search', 'nonexistent')->assertSee('No '.$resource.' found.');
})->with('classifications');

test('inactive users are denied even with classification permissions', function (string $resource, string $model) {
    $this->manager->update(['is_active' => false]);
    $this->actingAs($this->manager)->get(route($resource.'.index'))->assertForbidden();
    Livewire::actingAs($this->manager)->test(ClassificationTable::class, ['resource' => $resource])->assertForbidden();
})->with('classifications');

test('view permission does not grant write access', function (string $resource, string $model) {
    $role = Role::create(['name' => 'Viewer', 'slug' => 'viewer']);
    $role->permissions()->attach(Permission::where('slug', $resource.'.view')->firstOrFail());
    $this->manager->roles()->sync([$role->id]);
    $record = $model::factory()->create();
    $this->actingAs($this->manager)->get(route($resource.'.index'))->assertOk()->assertDontSee('Add '.str($resource)->singular());
    $this->post(route($resource.'.store'), ['name' => 'Denied', 'status' => 'active'])->assertForbidden();
    $this->delete(route($resource.'.destroy', ['record' => $record->id]))->assertForbidden();
})->with('classifications');

test('livewire rechecks permissions and locks the classification resource', function () {
    $component = Livewire::actingAs($this->manager)->test(ClassificationTable::class, ['resource' => 'categories']);
    expect(fn () => $component->set('resource', 'brands'))->toThrow(CannotUpdateLockedPropertyException::class);
    $component = Livewire::actingAs($this->manager)->test(ClassificationTable::class, ['resource' => 'categories']);
    $this->manager->roles()->detach();
    $component->set('search', 'query')->assertForbidden();
});

test('category children prevent deletion', function () {
    $parent = Category::factory()->create();
    Category::factory()->create(['parent_id' => $parent->id]);
    $this->actingAs($this->manager)->delete(route('categories.destroy', ['record' => $parent->id]))->assertSessionHasErrors('record');
});

test('permission seeding and access grants are idempotent and never create users', function () {
    $this->seed(ClassificationPermissionSeeder::class);
    expect(Permission::count())->toBe(12);
    $this->artisan('classification:grant', ['email' => $this->manager->email])->assertSuccessful();
    $this->artisan('classification:grant', ['email' => $this->manager->email])->assertSuccessful();
    expect($this->manager->roles()->count())->toBe(1);
    $this->artisan('classification:grant', ['email' => 'missing@example.com'])->assertFailed();
    expect(User::count())->toBe(1);
});

test('classification migration preserves legacy names codes and inactive status', function () {
    $migration = require database_path('migrations/2026_09_18_145354_add_classification_fields.php');
    $migration->down();
    $categoryId = DB::table('categories')->insertGetId(['name' => 'Legacy category', 'slug' => 'legacy', 'is_active' => false]);
    $unitId = DB::table('units')->insertGetId(['name' => 'Legacy unit', 'code' => 'kg', 'is_active' => false]);
    $migration->up();
    expect(Category::findOrFail($categoryId)->status)->toBe('inactive');
    expect(Unit::findOrFail($unitId)->short_name)->toBe('kg');
    expect(Unit::findOrFail($unitId)->status)->toBe('inactive');
});
