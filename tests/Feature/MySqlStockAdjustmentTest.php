<?php

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Role;
use App\Models\StockAdjustment;
use App\Models\StockItem;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\StockAdjustmentPermissionSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/** Uses only a newly created disposable database. */
test('mysql adjustments serialize stale counts and preserve duplicate submission safety', function () {
    $database = getenv('ADJUSTMENT_MYSQL_TEST_DATABASE');
    if (! $database) {
        $this->markTestSkipped('Set ADJUSTMENT_MYSQL_TEST_DATABASE=adjustment_test_<unique suffix> for isolated MySQL tests.');
    }
    if (! preg_match('/^adjustment_test_[a-z0-9_]{8,32}$/D', $database)) {
        throw new RuntimeException('A disposable adjustment_test_ database name is required.');
    }
    $connection = config('database.connections.mysql');
    $connection['url'] = null;
    $connection['database'] = $database;
    $previous = DB::getDefaultConnection();
    config(['database.connections.adjustment_test_admin' => [...$connection, 'database' => null]]);
    $admin = DB::connection('adjustment_test_admin');
    if ($admin->table('information_schema.schemata')->where('schema_name', $database)->exists()) {
        throw new RuntimeException('Refusing to use an existing database.');
    }
    $admin->statement('CREATE DATABASE '.$database.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    try {
        config(['database.connections.mysql' => $connection]);
        DB::purge('mysql');
        DB::setDefaultConnection('mysql');
        Artisan::call('migrate', ['--database' => 'mysql', '--force' => true, '--no-interaction' => true]);
        app(StockAdjustmentPermissionSeeder::class)->run();
        $actor = User::factory()->create(['is_active' => true]);
        $actor->roles()->attach(Role::where('slug', 'stock-adjustment-manager')->sole());
        $warehouse = Warehouse::factory()->create();
        $worker = <<<'PHP'
        require "vendor/autoload.php";
        $app = require "bootstrap/app.php";
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        try {
            Illuminate\Support\Facades\DB::transaction(function () {
                app(App\Services\StockAdjustmentService::class)->adjust([
                    "warehouse_id" => (int) getenv("ADJUST_TEST_WAREHOUSE"),
                    "stock_item_id" => (int) getenv("ADJUST_TEST_ITEM"),
                    "expected_quantity" => "100.1234", "new_quantity" => "97.1234",
                    "reason" => "Concurrent physical count", "idempotency_key" => getenv("ADJUST_TEST_KEY"),
                ], App\Models\User::findOrFail(getenv("ADJUST_TEST_ACTOR")));
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
            $inventory = Inventory::factory()->create(['stock_item_id' => $stock->id, 'warehouse_id' => $warehouse->id, 'quantity' => '100.1234', 'reserved_quantity' => '0']);
            $key = (string) Str::uuid();
            $environment = [
                'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_URL' => '',
                'DB_HOST' => (string) $connection['host'], 'DB_PORT' => (string) $connection['port'],
                'DB_DATABASE' => $database, 'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'],
                'CACHE_STORE' => 'array', 'ADJUST_TEST_WAREHOUSE' => (string) $warehouse->id, 'ADJUST_TEST_ITEM' => (string) $stock->id,
                'ADJUST_TEST_ACTOR' => (string) $actor->id,
            ];
            $workers = [
                new Process([PHP_BINARY, '-r', $worker], base_path(), [...$environment, 'ADJUST_TEST_KEY' => $key], timeout: 30),
                new Process([PHP_BINARY, '-r', $worker], base_path(), [...$environment, 'ADJUST_TEST_KEY' => $scenario === 'retry' ? $key : (string) Str::uuid()], timeout: 30),
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
            expect($inventory->fresh()->quantity)->toBe('97.1234');
            $movement = InventoryMovement::where('stock_item_id', $stock->id)->sole();
            expect($movement->quantity_before)->toBe('100.1234')->and($movement->quantity_after)->toBe('97.1234')
                ->and($movement->quantity_delta)->toBe('-3.0000');
            $adjustment = StockAdjustment::whereHas('items', fn (Builder $query): Builder => $query->where('stock_item_id', $stock->id))->sole();
            expect($adjustment->items()->sole()->difference)->toBe('-3.0000');
        }
    } finally {
        DB::disconnect('mysql');
        DB::setDefaultConnection($previous);
        $admin->statement('DROP DATABASE '.$database);
        DB::disconnect('adjustment_test_admin');
    }
});
