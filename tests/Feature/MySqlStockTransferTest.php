<?php

use App\InventoryMovementType;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\ProductBatch;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\StockTransferService;
use Database\Seeders\StockTransferPermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

test('mysql serializes duplicate and competing transfer completions', function () {
    $database = getenv('TRANSFER_MYSQL_TEST_DATABASE');
    if (! $database) {
        $this->markTestSkipped('Set TRANSFER_MYSQL_TEST_DATABASE=transfer_test_<unique suffix> for isolated MySQL tests.');
    }
    if (! preg_match('/^transfer_test_[a-z0-9_]{8,32}$/D', $database)) {
        throw new RuntimeException('A disposable transfer_test_ database name is required.');
    }
    $original = config('database.connections.mysql');
    $connection = [...$original, 'url' => null, 'database' => $database];
    $previous = DB::getDefaultConnection();
    config(['database.connections.transfer_test_admin' => [...$connection, 'database' => null]]);
    $admin = DB::connection('transfer_test_admin');
    if ($admin->table('information_schema.schemata')->where('schema_name', $database)->exists()) {
        throw new RuntimeException('Refusing to use an existing database.');
    }
    $admin->statement('CREATE DATABASE '.$database.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    try {
        config(['database.connections.mysql' => $connection]);
        DB::purge('mysql');
        DB::setDefaultConnection('mysql');
        Artisan::call('migrate', ['--database' => 'mysql', '--force' => true, '--no-interaction' => true]);
        app(StockTransferPermissionSeeder::class)->run();
        $actor = User::factory()->create(['is_active' => true]);
        $actor->roles()->attach(Role::where('slug', 'stock-transfer-manager')->sole());
        $source = Warehouse::factory()->create();
        $destination = Warehouse::factory()->create();
        $worker = <<<'PHP'
        require "vendor/autoload.php";
        $app = require "bootstrap/app.php";
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        try {
            Illuminate\Support\Facades\DB::transaction(function () {
                app(App\Services\StockTransferService::class)->transition(
                    App\Models\StockTransfer::findOrFail(getenv("TRANSFER_TEST_ID")),
                    App\StockTransferStatus::Completed,
                    App\Models\User::findOrFail(getenv("TRANSFER_TEST_ACTOR")), 1
                );
                usleep(200000);
            });
            echo "ok";
        } catch (Illuminate\Validation\ValidationException $exception) {
            echo "rejected";
            exit(2);
        }
        PHP;
        foreach (['retry', 'competing'] as $scenario) {
            $stock = StockItem::factory()->create();
            $inventory = app(InventoryService::class)->increaseStock(
                $stock, $source, '5.5001', InventoryMovementType::Purchase, $actor,
                unitCost: '12.3456', batch: ['batch_number' => 'MYSQL-LOT', 'expiration_date' => '2027-01-01'],
            );
            $data = ['source_warehouse_id' => $source->id, 'destination_warehouse_id' => $destination->id, 'transfer_date' => '2026-09-20', 'items' => [['stock_item_id' => $stock->id, 'quantity' => '4.0001']]];
            $first = app(StockTransferService::class)->saveDraft($data, $actor);
            $second = $scenario === 'retry' ? $first : app(StockTransferService::class)->saveDraft($data, $actor);
            $environment = [
                'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_URL' => '',
                'DB_HOST' => (string) $connection['host'], 'DB_PORT' => (string) $connection['port'],
                'DB_DATABASE' => $database, 'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'],
                'CACHE_STORE' => 'array', 'TRANSFER_TEST_ACTOR' => (string) $actor->id,
            ];
            $workers = [
                new Process([PHP_BINARY, '-r', $worker], base_path(), [...$environment, 'TRANSFER_TEST_ID' => (string) $first->id], timeout: 30),
                new Process([PHP_BINARY, '-r', $worker], base_path(), [...$environment, 'TRANSFER_TEST_ID' => (string) $second->id], timeout: 30),
            ];
            try {
                foreach ($workers as $process) {
                    $process->start();
                }
                $results = [];
                foreach ($workers as $process) {
                    $process->wait();
                    expect($process->getErrorOutput())->toBe('');
                    $results[] = trim($process->getOutput());
                }
            } finally {
                foreach ($workers as $process) {
                    if ($process->isRunning()) {
                        $process->stop();
                    }
                }
            }
            sort($results);
            expect($results)->toBe($scenario === 'retry' ? ['ok', 'ok'] : ['ok', 'rejected']);
            expect($inventory->fresh()->quantity)->toBe('1.5000')
                ->and(Inventory::where('stock_item_id', $stock->id)->where('warehouse_id', $destination->id)->sole()->quantity)->toBe('4.0001')
                ->and(InventoryMovement::where('stock_item_id', $stock->id)->count())->toBe(3)
                ->and(ProductBatch::where('stock_item_id', $stock->id)->where('warehouse_id', $source->id)->sole()->quantity)->toBe('1.5000')
                ->and(ProductBatch::where('stock_item_id', $stock->id)->where('warehouse_id', $destination->id)->sole()->quantity)->toBe('4.0001');
        }
    } finally {
        DB::disconnect('mysql');
        config(['database.connections.mysql' => $original]);
        DB::purge('mysql');
        DB::setDefaultConnection($previous);
        $admin->statement('DROP DATABASE '.$database);
        DB::disconnect('transfer_test_admin');
    }
});
