<?php

use App\InventoryMovementType;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\StockItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

/**
 * Opt in with INVENTORY_MYSQL_TEST_DATABASE=inventory_core_test_<unique suffix>.
 * Uses a newly created disposable database, never an existing database.
 */
test('mysql preserves decimal precision constraints and concurrent stock mutations', function () {
    $database = getenv('INVENTORY_MYSQL_TEST_DATABASE');
    if (! $database) {
        $this->markTestSkipped('Set INVENTORY_MYSQL_TEST_DATABASE to run isolated MySQL integration tests.');
    }
    if (! preg_match('/^inventory_core_test_[a-z0-9_]{8,32}$/D', $database)) {
        throw new RuntimeException('A disposable inventory_core_test_ database name is required.');
    }
    $connection = config('database.connections.mysql');
    $connection['url'] = null;
    $connection['database'] = $database;
    config(['database.connections.inventory_test_admin' => [...$connection, 'database' => null]]);
    $admin = DB::connection('inventory_test_admin');
    if ($admin->table('information_schema.schemata')->where('schema_name', $database)->exists()) {
        throw new RuntimeException('Refusing to use an existing test database.');
    }
    $admin->statement('CREATE DATABASE '.$database.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    try {
        config(['database.connections.mysql' => $connection]);
        DB::purge('mysql');
        DB::setDefaultConnection('mysql');
        Artisan::call('migrate', ['--database' => 'mysql', '--force' => true, '--no-interaction' => true]);
        $actor = User::factory()->create(['is_active' => true]);
        $warehouse = Warehouse::factory()->create();
        $item = StockItem::factory()->create();
        $service = app(InventoryService::class);
        $inventory = $service->increaseStock($item, $warehouse, '9999999999999999.9999', InventoryMovementType::Purchase, $actor);
        expect($inventory->quantity)->toBe('9999999999999999.9999');
        expect(fn () => $service->increaseStock($item, $warehouse, '0.0001', InventoryMovementType::Purchase, $actor))->toThrow(ValidationException::class);
        expect($service->decreaseStock($item, $warehouse, '0.0001', InventoryMovementType::Sale, $actor)->quantity)->toBe('9999999999999999.9998');
        expect(fn () => DB::table('inventories')->where('id', $inventory->id)->update(['quantity' => '-1']))->toThrow(QueryException::class);
        expect(fn () => DB::table('inventories')->insert(['stock_item_id' => $item->id, 'warehouse_id' => $warehouse->id]))->toThrow(QueryException::class);
        expect(fn () => DB::table('stock_items')->where('id', $item->id)->update(['product_id' => null]))->toThrow(QueryException::class);
        $variant = ProductVariant::factory()->create();
        expect(fn () => DB::table('stock_items')->where('id', $item->id)->update(['product_variant_id' => $variant->id]))->toThrow(QueryException::class);
        foreach (['cost_price', 'selling_price', 'reorder_level'] as $field) {
            expect(fn () => DB::table('stock_items')->where('id', $item->id)->update([$field => '-0.0001']))->toThrow(QueryException::class);
        }
        expect(fn () => DB::table('stock_items')->where('id', $item->id)->update(['product_id' => 999999999]))->toThrow(QueryException::class);
        expect(fn () => DB::table('stock_items')->insert(['sku' => 'NO-OWNER']))->toThrow(QueryException::class);
        $variantItem = StockItem::factory()->create(['product_id' => null, 'product_variant_id' => $variant->id, 'barcode' => 'AUDIT-BARCODE']);
        expect(fn () => DB::table('stock_items')->where('id', $item->id)->update(['sku' => $variantItem->sku]))->toThrow(QueryException::class);
        $item->update(['barcode' => 'OTHER-BARCODE']);
        expect(fn () => DB::table('stock_items')->where('id', $item->id)->update(['barcode' => $variantItem->barcode]))->toThrow(QueryException::class);

        $concurrentItem = StockItem::factory()->create();
        $worker = <<<'PHP'
        require "vendor/autoload.php";
        $app = require "bootstrap/app.php";
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $item = App\Models\StockItem::findOrFail(getenv("CORE_TEST_ITEM"));
        $warehouse = App\Models\Warehouse::findOrFail(getenv("CORE_TEST_WAREHOUSE"));
        $actor = App\Models\User::findOrFail(getenv("CORE_TEST_ACTOR"));
        try {
            Illuminate\Support\Facades\DB::transaction(function () use ($item, $warehouse, $actor) {
                $service = app(App\Services\InventoryService::class);
                if (getenv("CORE_TEST_OPERATION") === "increase") {
                    $service->increaseStock($item, $warehouse, "1", App\InventoryMovementType::Purchase, $actor);
                } else {
                    $service->decreaseStock($item, $warehouse, "1", App\InventoryMovementType::Sale, $actor);
                }
                usleep(200000);
            });
            echo "ok";
        } catch (Illuminate\Validation\ValidationException $exception) {
            echo "rejected";
            exit(2);
        }
        PHP;
        $environment = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_URL' => '',
            'DB_HOST' => (string) $connection['host'], 'DB_PORT' => (string) $connection['port'],
            'DB_DATABASE' => $database, 'DB_USERNAME' => $connection['username'],
            'DB_PASSWORD' => $connection['password'], 'CACHE_STORE' => 'array',
            'CORE_TEST_ITEM' => (string) $concurrentItem->id, 'CORE_TEST_WAREHOUSE' => (string) $warehouse->id,
            'CORE_TEST_ACTOR' => (string) $actor->id,
        ];
        foreach (['increase', 'decrease'] as $operation) {
            if ($operation === 'decrease') {
                $service->adjustStock($concurrentItem, $warehouse, '1', $actor, 'Concurrent sale fixture');
            }
            $workers = [
                new Process([PHP_BINARY, '-r', $worker], base_path(), [...$environment, 'CORE_TEST_OPERATION' => $operation], timeout: 30),
                new Process([PHP_BINARY, '-r', $worker], base_path(), [...$environment, 'CORE_TEST_OPERATION' => $operation], timeout: 30),
            ];
            try {
                foreach ($workers as $process) {
                    $process->start();
                }
                $results = [];
                foreach ($workers as $process) {
                    $process->wait();
                    $results[] = trim($process->getOutput());
                    expect($process->getErrorOutput())->toBe('');
                }
            } finally {
                foreach ($workers as $process) {
                    if ($process->isRunning()) {
                        $process->stop();
                    }
                }
            }
            sort($results);
            expect($results)->toBe($operation === 'increase' ? ['ok', 'ok'] : ['ok', 'rejected']);
            expect(Inventory::where('stock_item_id', $concurrentItem->id)->sole()->quantity)->toBe($operation === 'increase' ? '2.0000' : '0.0000');
        }
        expect(Inventory::where('stock_item_id', $concurrentItem->id)->count())->toBe(1);
        expect(InventoryMovement::where('stock_item_id', $concurrentItem->id)->count())->toBe(4);
    } finally {
        DB::disconnect('mysql');
        $admin->statement('DROP DATABASE '.$database);
        DB::disconnect('inventory_test_admin');
    }
});
