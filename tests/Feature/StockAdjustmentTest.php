<?php

use App\InventoryMovementType as Movement;
use App\Livewire\StockAdjustmentForm;
use App\Livewire\StockAdjustmentTable;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Permission;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\StockAdjustmentService;
use App\StockAdjustmentStatus;
use Database\Seeders\StockAdjustmentPermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StockAdjustmentPermissionSeeder::class);
    $this->actor = User::factory()->create(['is_active' => true]);
    $this->actor->roles()->attach(Role::where('slug', 'stock-adjustment-manager')->sole());
    $this->stockItem = StockItem::factory()->create(['sku' => 'COUNT-SKU', 'barcode' => '123456789']);
    $this->warehouse = Warehouse::factory()->create();
    $this->inventory = Inventory::factory()->create(['stock_item_id' => $this->stockItem->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => '100', 'reserved_quantity' => '0']);
    $this->data = ['stock_item_id' => $this->stockItem->id, 'warehouse_id' => $this->warehouse->id,
        'expected_quantity' => '100', 'new_quantity' => '97', 'reason' => 'Damage found during physical count', 'idempotency_key' => (string) Str::uuid()];
    $this->service = app(StockAdjustmentService::class);
});

test('adjustments record signed differences and delegate stock changes to inventory service', function (string $new, string $delta, Movement $type) {
    $this->partialMock(InventoryService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('adjustStock')->once()->passthru();
    });
    $adjustment = $this->service->adjust([...$this->data, 'new_quantity' => $new, 'created_by' => 999, 'difference' => '999'], $this->actor);
    $line = $adjustment->items()->sole();
    $movement = InventoryMovement::sole();
    expect($adjustment->status)->toBe(StockAdjustmentStatus::Completed)->and($adjustment->created_by)->toBe($this->actor->id)
        ->and($adjustment->created_at)->not->toBeNull()->and($adjustment->warehouse_id)->toBe($this->warehouse->id)
        ->and($line->previous_quantity)->toBe('100.0000')->and($line->new_quantity)->toBe($new)
        ->and($line->difference)->toBe($delta)->and($line->reason)->toBe($this->data['reason'])
        ->and($this->inventory->fresh()->quantity)->toBe($new)
        ->and($movement->type)->toBe($type)->and($movement->quantity_delta)->toBe($delta)
        ->and($movement->quantity_before)->toBe('100.0000')->and($movement->quantity_after)->toBe($new)
        ->and($movement->performed_by)->toBe($this->actor->id)->and($movement->reason)->toBe($this->data['reason'])
        ->and($movement->reference_type)->toBe(StockAdjustment::class)->and($movement->reference_id)->toBe($adjustment->id)
        ->and($movement->occurred_at)->not->toBeNull()->and($adjustment->movements()->sole()->id)->toBe($movement->id);
})->with([['97.0000', '-3.0000', Movement::AdjustmentOut], ['105.0000', '5.0000', Movement::AdjustmentIn], ['0.0000', '-100.0000', Movement::AdjustmentOut]]);

test('invalid adjustment input never changes inventory or creates audit records', function (string $field, mixed $value) {
    expect(fn () => $this->service->adjust([...$this->data, $field => $value], $this->actor))->toThrow(ValidationException::class);
    expect($this->inventory->fresh()->quantity)->toBe('100.0000');
    foreach (['stock_adjustments', 'stock_adjustment_items', 'inventory_movements'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
})->with([
    ['new_quantity', '-1'], ['new_quantity', '1.00001'], ['new_quantity', '1e2'], ['new_quantity', ''], ['new_quantity', 'abc'],
    ['new_quantity', '10000000000000000'], ['new_quantity', '100'], ['expected_quantity', '-1'], ['expected_quantity', '99'],
    ['reason', ''], ['reason', " \t\n"], ['reason', null], ['reason', str_repeat('x', 256)],
    ['stock_item_id', 999999], ['warehouse_id', 999999], ['idempotency_key', 'invalid'],
]);

test('fractions are exact and numeric input does not get truncated', function () {
    $result = $this->service->adjust([...$this->data, 'new_quantity' => 97.1234, 'reason' => '  Physical count  '], $this->actor);
    expect($result->items()->sole()->difference)->toBe('-2.8766')->and($result->items()->sole()->reason)->toBe('Physical count')
        ->and($this->inventory->fresh()->quantity)->toBe('97.1234');
});

test('adjustments cannot consume reserved inventory', function () {
    $this->inventory->forceFill(['reserved_quantity' => '98'])->save();
    expect(fn () => $this->service->adjust($this->data, $this->actor))->toThrow(ValidationException::class);
    expect($this->inventory->fresh()->quantity)->toBe('100.0000')->and($this->inventory->fresh()->reserved_quantity)->toBe('98.0000');
    $this->assertDatabaseCount('stock_adjustments', 0);
    $this->assertDatabaseCount('inventory_movements', 0);
    $this->service->adjust([...$this->data, 'new_quantity' => '98'], $this->actor);
    expect($this->inventory->fresh()->quantity)->toBe('98.0000');
});

test('missing inventory and variants can be adjusted with warehouse isolation', function () {
    $variant = ProductVariant::factory()->create();
    $stock = StockItem::factory()->create(['product_id' => null, 'product_variant_id' => $variant->id]);
    $other = Warehouse::factory()->create();
    $adjustment = $this->service->adjust([...$this->data, 'stock_item_id' => $stock->id, 'warehouse_id' => $other->id, 'expected_quantity' => '0', 'new_quantity' => '5'], $this->actor);
    expect($adjustment->items()->sole()->previous_quantity)->toBe('0.0000')
        ->and(Inventory::where('warehouse_id', $other->id)->sole()->quantity)->toBe('5.0000')
        ->and($this->inventory->fresh()->quantity)->toBe('100.0000');
});

test('inactive warehouses products variants and stock items cannot be adjusted', function (string $target) {
    match ($target) {
        'warehouse' => $this->warehouse->update(['status' => 'inactive']),
        'product' => $this->stockItem->product->update(['status' => 'inactive']),
        'stock' => $this->stockItem->update(['is_active' => false]),
    };
    expect(fn () => $this->service->adjust($this->data, $this->actor))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('stock_adjustments', 0);
})->with(['warehouse', 'product', 'stock']);

test('intervening inventory changes require a fresh count review', function () {
    app(InventoryService::class)->decreaseStock($this->stockItem, $this->warehouse, '1', Movement::Damage, $this->actor);
    expect(fn () => $this->service->adjust($this->data, $this->actor))->toThrow(ValidationException::class);
    expect($this->inventory->fresh()->quantity)->toBe('99.0000');
    $this->assertDatabaseCount('stock_adjustments', 0);
    $this->assertDatabaseCount('inventory_movements', 1);
});

test('submission retries do not reapply an adjustment even if stock later returns to its original value', function () {
    $first = $this->service->adjust($this->data, $this->actor);
    expect($this->service->adjust($this->data, $this->actor)->id)->toBe($first->id);
    app(InventoryService::class)->increaseStock($this->stockItem, $this->warehouse, '3', Movement::Purchase, $this->actor);
    expect($this->service->adjust($this->data, $this->actor)->id)->toBe($first->id)
        ->and($this->inventory->fresh()->quantity)->toBe('100.0000');
    expect(fn () => $this->service->adjust([...$this->data, 'reason' => 'Changed submission'], $this->actor))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('stock_adjustments', 1);
    $this->assertDatabaseCount('inventory_movements', 2);
});

test('failed movement or audit writes roll back the entire stock adjustment', function (string $model) {
    Event::listen('eloquent.creating: '.$model, function (): void {
        throw new RuntimeException('Simulated adjustment failure');
    });
    try {
        expect(fn () => $this->service->adjust($this->data, $this->actor))->toThrow(RuntimeException::class, 'Simulated adjustment failure');
    } finally {
        Event::forget('eloquent.creating: '.$model);
    }
    expect($this->inventory->fresh()->quantity)->toBe('100.0000');
    foreach (['stock_adjustments', 'stock_adjustment_items', 'inventory_movements'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
})->with([InventoryMovement::class, StockAdjustmentItem::class]);

test('adjustment audit records cannot be edited or deleted', function () {
    $adjustment = $this->service->adjust($this->data, $this->actor);
    foreach ([$adjustment, $adjustment->items()->sole()] as $model) {
        expect($model->getGuarded())->toBe(['*']);
        expect(fn () => $model->forceFill(['reason' => 'Rewritten'])->save())->toThrow(LogicException::class);
        expect(fn () => $model->delete())->toThrow(LogicException::class);
    }
    expect($this->inventory->fresh()->quantity)->toBe('97.0000');
});

test('livewire previews physical counts differences and records a confirmed adjustment', function () {
    $component = Livewire::actingAs($this->actor)->test(StockAdjustmentForm::class)
        ->set('warehouseId', (string) $this->warehouse->id)->set('search', '123456789')->assertSee('COUNT-SKU')
        ->call('selectItem', $this->stockItem->id)->assertSet('currentQuantity', '100.0000')
        ->set('newQuantity', '105')->assertViewHas('difference', '5.0000')->assertSee('ADJUSTMENT_IN')
        ->set('newQuantity', '97')->assertViewHas('difference', '-3.0000')->assertSee('ADJUSTMENT_OUT')
        ->set('reason', 'Expired goods removed')->call('save')->assertHasNoErrors();
    $adjustment = StockAdjustment::sole();
    $component->assertRedirect(route('stock-adjustments.show', $adjustment));
    $this->get(route('stock-adjustments.show', $adjustment))->assertOk()->assertSee('Expired goods removed')->assertSee('ADJUSTMENT_OUT')->assertSee('Quantity 3.0000');
    expect($this->inventory->fresh()->quantity)->toBe('97.0000');
});

test('livewire rejects missing reasons and stale balances and refresh requires a new count', function () {
    $component = Livewire::actingAs($this->actor)->test(StockAdjustmentForm::class)
        ->set('warehouseId', (string) $this->warehouse->id)->call('selectItem', $this->stockItem->id)
        ->set('newQuantity', '97')->call('save')->assertHasErrors('reason')->set('reason', 'Physical count');
    app(InventoryService::class)->decreaseStock($this->stockItem, $this->warehouse, '1', Movement::Damage, $this->actor);
    $component->call('save')->assertHasErrors('expected_quantity')
        ->call('refreshStock')->assertSet('currentQuantity', '99.0000')->assertSet('newQuantity', '')
        ->set('warehouseId', (string) Warehouse::factory()->create()->id)
        ->assertSet('stockItemId', null)->assertSet('currentQuantity', null)->call('save')->assertHasErrors('expected_quantity');
    $this->assertDatabaseCount('stock_adjustments', 0);
});

test('guests unauthorized and view-only users cannot submit adjustments', function () {
    $adjustment = StockAdjustment::factory()->create();
    $this->get(route('stock-adjustments.create'))->assertRedirect(route('login'));
    $outsider = User::factory()->create(['is_active' => true]);
    $this->actingAs($outsider);
    foreach (['index', 'create', 'show'] as $action) {
        $this->get(route('stock-adjustments.'.$action, $adjustment))->assertForbidden();
    }
    Livewire::test(StockAdjustmentForm::class)->assertForbidden();
    Livewire::test(StockAdjustmentTable::class)->assertForbidden();
    expect(fn () => $this->service->adjust($this->data, $outsider))->toThrow(AuthorizationException::class);
    $role = Role::create(['name' => 'Adjustment Viewer', 'slug' => 'adjustment-viewer']);
    $role->permissions()->attach(Permission::where('slug', 'adjustments.view')->sole());
    $outsider->roles()->attach($role);
    $this->get(route('stock-adjustments.index'))->assertOk()->assertDontSee('New adjustment');
    Livewire::test(StockAdjustmentForm::class)->assertForbidden();
    $this->actor->update(['is_active' => false]);
    expect(fn () => $this->service->adjust($this->data, $this->actor))->toThrow(AuthorizationException::class);
});

test('permissions are rechecked on livewire submission', function () {
    $component = Livewire::actingAs($this->actor)->test(StockAdjustmentForm::class)
        ->set('warehouseId', (string) $this->warehouse->id)->call('selectItem', $this->stockItem->id)
        ->set('newQuantity', '97')->set('reason', 'Physical count');
    $this->actor->roles()->detach();
    $component->call('save')->assertForbidden();
    expect($this->inventory->fresh()->quantity)->toBe('100.0000');
});

test('history supports pagination and combined search and warehouse filtering', function () {
    StockAdjustmentItem::factory()->count(11)->create();
    $adjustment = $this->service->adjust($this->data, $this->actor);
    $this->actingAs($this->actor)->get(route('stock-adjustments.index'))->assertOk()->assertSeeLivewire(StockAdjustmentTable::class);
    $this->get(route('stock-adjustments.create'))->assertOk()->assertSeeLivewire(StockAdjustmentForm::class);
    Livewire::test(StockAdjustmentTable::class)->assertViewHas('adjustments', fn ($rows): bool => $rows->total() === 12)
        ->call('setPage', 2)->set('search', 'COUNT-SKU')
        ->assertViewHas('adjustments', fn ($rows): bool => $rows->currentPage() === 1 && $rows->total() === 1)
        ->set('warehouse', (string) Warehouse::factory()->create()->id)->assertSee('No stock adjustments match your filters.')
        ->call('clearFilters')->set('search', $adjustment->number)->assertSee($this->data['reason']);
});

test('adjustment grants are idempotent and require existing active users', function () {
    $this->seed(StockAdjustmentPermissionSeeder::class);
    expect(Permission::count())->toBe(2);
    $this->artisan('adjustments:grant', ['email' => $this->actor->email])->assertSuccessful();
    $this->artisan('adjustments:grant', ['email' => $this->actor->email])->assertSuccessful();
    expect($this->actor->roles()->count())->toBe(1);
    $this->artisan('adjustments:grant', ['email' => 'missing@example.test'])->assertFailed();
    $this->actor->update(['is_active' => false]);
    $this->artisan('adjustments:grant', ['email' => $this->actor->email])->assertFailed();
});
