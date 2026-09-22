<?php

use App\Livewire\OperationsDashboard;
use App\Livewire\ReportTable;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Permission;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ReportQuery;
use Database\Seeders\ReportPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(ReportPermissionSeeder::class);
    $this->viewer = User::factory()->create(['is_active' => true]);
    $this->viewer->roles()->attach(Role::where('slug', 'report-viewer')->sole());
    $this->warehouse = Warehouse::factory()->create();
    $this->item = StockItem::factory()->create(['sku' => 'REPORT-SKU', 'cost_price' => '5', 'reorder_level' => '10']);
    $this->category = (string) $this->item->product->category_id;
    $this->reports = app(ReportQuery::class);
});

test('every report enforces authentication authorization and renders', function (string $report) {
    $url = route('reports.show', $report);
    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    $this->actingAs($this->viewer)->get($url)->assertOk()->assertSeeLivewire(ReportTable::class);
    Livewire::test(ReportTable::class, ['report' => $report])->assertViewHas('rows');
    $this->viewer->roles()->detach();
    Livewire::test(ReportTable::class, ['report' => $report])->assertForbidden();
})->with(array_keys(ReportQuery::TYPES));

test('report permissions remain independent and report identity is locked', function () {
    $role = Role::create(['slug' => 'inventory-report-only', 'name' => 'Inventory report only']);
    $role->permissions()->attach(Permission::where('slug', 'reports.inventory')->sole());
    $this->viewer->roles()->sync([$role->id]);
    $this->actingAs($this->viewer)->get(route('reports.index'))->assertOk()->assertSee('Inventory Report')->assertDontSee('Inventory Valuation');
    $this->get(route('reports.show', 'valuation'))->assertForbidden();
    $this->get(route('dashboard'))->assertOk()->assertDontSeeLivewire(OperationsDashboard::class);
    Livewire::test(OperationsDashboard::class)->assertForbidden();
    $component = Livewire::test(ReportTable::class, ['report' => 'inventory']);
    expect(fn () => $component->set('report', 'valuation'))->toThrow(CannotUpdateLockedPropertyException::class);
    $this->get(route('reports.show', 'invalid'))->assertNotFound();
});

test('current inventory and low stock include virtual zeros and warehouse overrides', function () {
    $other = Warehouse::factory()->create();
    Inventory::factory()->create(['stock_item_id' => $this->item->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => '10', 'reserved_quantity' => '2']);
    expect($this->reports->query('inventory')->count())->toBe(2)
        ->and($this->reports->query('low-stock')->count())->toBe(2);
    $row = $this->reports->query('inventory', ['warehouse' => (string) $this->warehouse->id])->sole();
    expect(ReportQuery::decimal($row->available_quantity))->toBe('8.0000')->and(ReportQuery::decimal($row->effective_reorder_level))->toBe('10.0000');
    Inventory::where('warehouse_id', $this->warehouse->id)->update(['reorder_level' => '9']);
    expect($this->reports->query('low-stock')->count())->toBe(1);
    $this->assertDatabaseCount('inventories', 1);
});

test('valuation combines remaining batch cost with untracked catalog cost without duplicating balances', function () {
    Inventory::factory()->create(['stock_item_id' => $this->item->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => '10']);
    foreach ([['3', '2'], ['2', '4']] as [$quantity, $cost]) {
        ProductBatch::factory()->create(['stock_item_id' => $this->item->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => $quantity, 'unit_cost' => $cost]);
    }
    $row = $this->reports->query('valuation')->sole();
    expect(ReportQuery::decimal($row->valuation))->toBe('39.0000');
    Livewire::actingAs($this->viewer)->test(ReportTable::class, ['report' => 'valuation'])->assertViewHas('valuation', '39.0000')->assertSee('39.0000');
    $data = $this->reports->dashboard();
    expect($data['cards']['Inventory value'])->toBe('39.0000')->and($data['cards']['Total inventory quantity'])->toBe('10.0000');
});

test('category search and warehouse filters intersect for variants', function () {
    $variant = ProductVariant::factory()->create(['name' => 'Blue XL']);
    $stock = StockItem::factory()->create(['product_id' => null, 'product_variant_id' => $variant->id, 'barcode' => '998877']);
    $other = Warehouse::factory()->create();
    $rows = $this->reports->query('inventory', ['category' => (string) $variant->product->category_id, 'warehouse' => (string) $other->id, 'search' => 'Blue XL'])->get();
    expect($rows)->toHaveCount(1)->and($rows[0]->item_id)->toBe($stock->id);
    expect($this->reports->query('inventory', ['category' => $this->category, 'search' => '998877'])->count())->toBe(0);
});

test('document reports filter matching lines rather than counting entire documents', function () {
    $other = StockItem::factory()->create();
    $order = PurchaseOrder::factory()->create(['warehouse_id' => $this->warehouse->id, 'ordered_at' => '2026-09-20']);
    foreach ([$this->item, $other] as $item) {
        PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'stock_item_id' => $item->id]);
    }
    $adjustment = StockAdjustment::factory()->create(['warehouse_id' => $this->warehouse->id, 'created_at' => '2026-09-20 23:59:59']);
    StockAdjustmentItem::factory()->create(['stock_adjustment_id' => $adjustment->id, 'stock_item_id' => $this->item->id]);
    $destination = Warehouse::factory()->create();
    $transfer = StockTransfer::factory()->create(['source_warehouse_id' => $this->warehouse->id, 'destination_warehouse_id' => $destination->id, 'transfer_date' => '2026-09-20']);
    StockTransferItem::factory()->create(['stock_transfer_id' => $transfer->id, 'stock_item_id' => $this->item->id]);
    foreach (['purchases', 'adjustments', 'transfers'] as $report) {
        $filters = ['category' => $this->category, 'search' => 'REPORT-SKU', 'from' => '2026-09-20', 'to' => '2026-09-20', 'warehouse' => (string) $this->warehouse->id];
        expect($this->reports->query($report, $filters)->count())->toBe(1);
        expect($this->reports->query($report, [...$filters, 'to' => '2026-09-19'])->count())->toBe(0);
        Livewire::actingAs($this->viewer)->test(ReportTable::class, ['report' => $report])->assertSee('REPORT-SKU');
    }
    expect($this->reports->query('transfers', ['warehouse' => (string) $destination->id])->count())->toBe(1);
});

test('movement dates are inclusive and deterministic and show audit fields', function () {
    InventoryMovement::factory()->create(['stock_item_id' => $this->item->id, 'warehouse_id' => $this->warehouse->id, 'occurred_at' => '2026-09-20 23:59:59', 'reason' => 'Audit reason']);
    InventoryMovement::factory()->create(['stock_item_id' => $this->item->id, 'warehouse_id' => $this->warehouse->id, 'occurred_at' => '2026-09-21 00:00:00']);
    Livewire::actingAs($this->viewer)->test(ReportTable::class, ['report' => 'movements'])
        ->set('from', '2026-09-20')->set('to', '2026-09-20')->assertViewHas('rows', fn ($rows) => $rows->total() === 1)->assertSee('Audit reason');
});

test('expiration windows exclude depleted stock and apply warehouse category and custom date filters', function () {
    $this->travelTo(now()->setDate(2026, 9, 20)->startOfDay());
    foreach ([-1, 0, 7, 8, 30, 31] as $days) {
        ProductBatch::factory()->create(['stock_item_id' => $this->item->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => '1', 'expiration_date' => today()->addDays($days)]);
    }
    ProductBatch::factory()->create(['stock_item_id' => $this->item->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => '0', 'expiration_date' => today()]);
    expect($this->reports->query('expiration', ['period' => '7'])->count())->toBe(2)
        ->and($this->reports->query('expiration', ['period' => '30'])->count())->toBe(4)
        ->and($this->reports->query('expiration', ['period' => 'expired'])->count())->toBe(1)
        ->and($this->reports->query('expiration', ['period' => 'all', 'from' => '2026-09-28', 'to' => '2026-09-28', 'category' => $this->category])->count())->toBe(1);
});

test('filters validate gracefully and reset pagination without loading the whole dataset', function () {
    StockItem::factory()->count(30)->create();
    $component = Livewire::actingAs($this->viewer)->test(ReportTable::class, ['report' => 'inventory'])
        ->assertViewHas('rows', fn ($rows) => $rows->count() === 25 && $rows->total() === 31)
        ->call('setPage', 2)->set('search', 'REPORT-SKU')->assertViewHas('rows', fn ($rows) => $rows->currentPage() === 1 && $rows->total() === 1)
        ->set('warehouse', 'invalid')->assertHasErrors('warehouse')->assertViewHas('rows', null)
        ->call('clearFilters')->assertHasNoErrors();
    Livewire::test(ReportTable::class, ['report' => 'movements'])->set('from', '2026-09-20')->set('to', '2026-09-19')->assertHasErrors('to');
});

test('dashboard aggregates distinct products and limits recent lists', function () {
    Inventory::factory()->create(['stock_item_id' => $this->item->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => '5']);
    InventoryMovement::factory()->count(12)->create(['stock_item_id' => $this->item->id, 'warehouse_id' => $this->warehouse->id]);
    PurchaseOrder::factory()->count(12)->create(['warehouse_id' => $this->warehouse->id]);
    ProductBatch::factory()->count(2)->create(['stock_item_id' => $this->item->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => '1', 'expiration_date' => today()->addDays(7)]);
    $data = $this->reports->dashboard();
    expect($data['cards']['Total products'])->toBe(1)->and($data['cards']['Low-stock products'])->toBe(1)
        ->and($data['cards']['Expiring products (30 days)'])->toBe(1)->and($data['movements'])->toHaveCount(10)->and($data['purchases'])->toHaveCount(10);
    $this->actingAs($this->viewer)->get(route('dashboard'))->assertOk()->assertSeeLivewire(OperationsDashboard::class)->assertSee('Recent purchases');
    Livewire::test(OperationsDashboard::class)->set('warehouse', 'invalid')->assertHasErrors('warehouse');
});

test('report rendering has bounded query counts and does not mutate stock', function () {
    InventoryMovement::factory()->count(30)->create(['stock_item_id' => $this->item->id, 'warehouse_id' => $this->warehouse->id]);
    DB::enableQueryLog();
    Livewire::actingAs($this->viewer)->test(ReportTable::class, ['report' => 'movements'])->assertViewHas('rows', fn ($rows) => $rows->count() === 25);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect(count($queries))->toBeLessThan(35);
    $this->assertDatabaseCount('inventories', 0);
    $this->assertDatabaseCount('inventory_movements', 30);
});

test('report access seeding and grants are idempotent and reject inactive users', function () {
    $this->seed(ReportPermissionSeeder::class);
    $this->artisan('reports:grant', ['email' => $this->viewer->email])->assertSuccessful();
    expect($this->viewer->roles()->count())->toBe(1);
    $this->assertDatabaseCount('permissions', 9);
    $this->viewer->update(['is_active' => false]);
    $this->artisan('reports:grant', ['email' => $this->viewer->email])->assertFailed();
    Livewire::actingAs($this->viewer)->test(OperationsDashboard::class)->assertForbidden();
});
