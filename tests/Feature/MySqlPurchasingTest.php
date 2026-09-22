<?php

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\ProductBatch;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\PurchaseOrderStatus;
use App\Services\PurchaseOrderService;
use Database\Seeders\PurchasingPermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/** Uses a newly created disposable database, never the application's existing database. */
test('mysql purchasing preserves exact totals and serializes competing receipts and retries', function () {
    $database = getenv('PURCHASING_MYSQL_TEST_DATABASE');
    if (! $database) {
        $this->markTestSkipped('Set PURCHASING_MYSQL_TEST_DATABASE to purchasing_test_<unique suffix> for isolated MySQL tests.');
    }
    if (! preg_match('/^purchasing_test_[a-z0-9_]{8,32}$/D', $database)) {
        throw new RuntimeException('A disposable purchasing_test_ database name is required.');
    }
    $connection = config('database.connections.mysql');
    $connection['url'] = null;
    $connection['database'] = $database;
    $previous = DB::getDefaultConnection();
    config(['database.connections.purchasing_test_admin' => [...$connection, 'database' => null]]);
    $admin = DB::connection('purchasing_test_admin');
    if ($admin->table('information_schema.schemata')->where('schema_name', $database)->exists()) {
        throw new RuntimeException('Refusing to use an existing database.');
    }
    $admin->statement('CREATE DATABASE '.$database.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    try {
        config(['database.connections.mysql' => $connection]);
        DB::purge('mysql');
        DB::setDefaultConnection('mysql');
        Artisan::call('migrate', ['--database' => 'mysql', '--force' => true, '--no-interaction' => true]);
        app(PurchasingPermissionSeeder::class)->run();
        $actor = User::factory()->create(['is_active' => true]);
        $actor->roles()->attach(Role::where('slug', 'purchasing-manager')->sole());
        $supplier = Supplier::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $service = app(PurchaseOrderService::class);
        $worker = <<<'PHP'
        require "vendor/autoload.php";
        $app = require "bootstrap/app.php";
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        try {
            Illuminate\Support\Facades\DB::transaction(function () {
                app(App\Services\ReceivingService::class)->receive(
                    App\Models\PurchaseOrder::findOrFail(getenv("PURCHASE_TEST_ORDER")),
                    ["idempotency_key" => getenv("PURCHASE_TEST_KEY"), "items" => [
                        ["purchase_order_item_id" => (int) getenv("PURCHASE_TEST_ITEM"), "quantity" => getenv("PURCHASE_TEST_QUANTITY"),
                         "batch_number" => "CONCURRENT-RECEIPT-LOT", "expiration_date" => "2027-01-01"],
                    ]],
                    App\Models\User::findOrFail(getenv("PURCHASE_TEST_ACTOR")),
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
            $order = $service->saveDraft([
                'supplier_id' => $supplier->id, 'warehouse_id' => $warehouse->id, 'ordered_at' => '2026-09-19',
                'items' => [['stock_item_id' => $stock->id, 'ordered_quantity' => $scenario === 'retry' ? '1' : '2.5',
                    'unit_cost' => $scenario === 'retry' ? '9999999999999999.9999' : '0.1234', 'discount' => '0', 'tax' => '0']],
            ], $actor);
            expect($order->total)->toBe($scenario === 'retry' ? '9999999999999999.9999' : '0.3085');
            $service->transition($order, PurchaseOrderStatus::Ordered, $actor);
            expect(Inventory::where('stock_item_id', $stock->id)->count())->toBe(0);
            $line = $order->items()->sole();
            $key = (string) Str::uuid();
            $environment = [
                'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_URL' => '',
                'DB_HOST' => (string) $connection['host'], 'DB_PORT' => (string) $connection['port'],
                'DB_DATABASE' => $database, 'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'],
                'CACHE_STORE' => 'array', 'PURCHASE_TEST_ORDER' => (string) $order->id, 'PURCHASE_TEST_ITEM' => (string) $line->id,
                'PURCHASE_TEST_ACTOR' => (string) $actor->id, 'PURCHASE_TEST_QUANTITY' => $scenario === 'retry' ? '1' : '2',
            ];
            $workers = [
                new Process([PHP_BINARY, '-r', $worker], base_path(), [...$environment, 'PURCHASE_TEST_KEY' => $key], timeout: 30),
                new Process([PHP_BINARY, '-r', $worker], base_path(), [...$environment, 'PURCHASE_TEST_KEY' => $scenario === 'retry' ? $key : (string) Str::uuid()], timeout: 30),
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
            expect($results)->toBe($scenario === 'retry' ? ['ok', 'ok'] : ['ok', 'rejected']);
            expect(Inventory::where('stock_item_id', $stock->id)->sole()->quantity)->toBe($scenario === 'retry' ? '1.0000' : '2.0000');
            expect(PurchaseOrderItem::findOrFail($line->id)->received_quantity)->toBe($scenario === 'retry' ? '1.0000' : '2.0000');
            expect($order->fresh()->status)->toBe($scenario === 'retry' ? PurchaseOrderStatus::Received : PurchaseOrderStatus::PartiallyReceived);
            expect(PurchaseReceipt::where('purchase_order_id', $order->id)->count())->toBe(1);
            expect(InventoryMovement::where('stock_item_id', $stock->id)->count())->toBe(1);
            $batch = ProductBatch::where('stock_item_id', $stock->id)->sole();
            expect($batch->quantity)->toBe($scenario === 'retry' ? '1.0000' : '2.0000')
                ->and($batch->unit_cost)->toBe($line->unit_cost)
                ->and(InventoryMovement::where('stock_item_id', $stock->id)->sole()->product_batch_id)->toBe($batch->id);
        }
    } finally {
        DB::disconnect('mysql');
        DB::setDefaultConnection($previous);
        $admin->statement('DROP DATABASE '.$database);
        DB::disconnect('purchasing_test_admin');
    }
});
