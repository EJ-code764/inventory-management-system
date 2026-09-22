<?php

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\ProductBatch;
use App\Models\PurchaseOrderItem;
use App\Models\StockAdjustmentItem;
use App\Models\StockItem;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use App\Services\ReportQuery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('mysql report queries preserve decimal valuation and support all report shapes', function () {
    $database = getenv('REPORTS_MYSQL_TEST_DATABASE');
    if (! $database) {
        $this->markTestSkipped('Set REPORTS_MYSQL_TEST_DATABASE=reports_test_<unique suffix> for isolated MySQL report tests.');
    }
    if (! preg_match('/^reports_test_[a-z0-9_]{8,32}$/D', $database)) {
        throw new RuntimeException('A disposable reports_test_ database name is required.');
    }
    $original = config('database.connections.mysql');
    $connection = [...$original, 'url' => null, 'database' => $database];
    $previous = DB::getDefaultConnection();
    config(['database.connections.reports_test_admin' => [...$connection, 'database' => null]]);
    $admin = DB::connection('reports_test_admin');
    if ($admin->table('information_schema.schemata')->where('schema_name', $database)->exists()) {
        throw new RuntimeException('Refusing to use an existing database.');
    }
    $admin->statement('CREATE DATABASE '.$database.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    try {
        config(['database.connections.mysql' => $connection]);
        DB::purge('mysql');
        DB::setDefaultConnection('mysql');
        Artisan::call('migrate', ['--database' => 'mysql', '--force' => true, '--no-interaction' => true]);
        $item = StockItem::factory()->create(['cost_price' => '9999999999999999.9999']);
        $warehouse = Warehouse::factory()->create();
        Inventory::factory()->create(['stock_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => '1', 'reserved_quantity' => '0']);
        ProductBatch::factory()->create(['stock_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => '0.2500', 'unit_cost' => '2.0000']);
        InventoryMovement::factory()->create(['stock_item_id' => $item->id, 'warehouse_id' => $warehouse->id]);
        PurchaseOrderItem::factory()->create(['stock_item_id' => $item->id]);
        StockAdjustmentItem::factory()->create(['stock_item_id' => $item->id]);
        StockTransferItem::factory()->create(['stock_item_id' => $item->id]);
        $reports = app(ReportQuery::class);
        $row = $reports->query('valuation')->sole();
        expect(ReportQuery::decimal($row->valuation))->toBe('7500000000000000.4999')
            ->and($reports->dashboard()['cards']['Inventory value'])->toBe('7500000000000000.4999');
        foreach (array_keys(ReportQuery::TYPES) as $report) {
            $query = $reports->query($report, ['search' => $item->sku, 'category' => (string) $item->product->category_id]);
            if (isset(ReportQuery::DATES[$report])) {
                $query->orderByDesc('date')->orderByDesc('report_row_id');
            }
            expect($query->paginate(25)->count())->toBeGreaterThan(0);
        }
        $migration = require database_path('migrations/2026_09_20_062623_add_adjustment_report_date_index.php');
        $migration->down();
        $migration->up();
        expect(Schema::hasIndex('stock_adjustments', 'adjustments_chronology_index'))->toBeTrue();
    } finally {
        DB::disconnect('mysql');
        config(['database.connections.mysql' => $original]);
        DB::purge('mysql');
        DB::setDefaultConnection($previous);
        $admin->statement('DROP DATABASE '.$database);
        DB::disconnect('reports_test_admin');
    }
});
