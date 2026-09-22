<?php

use App\InventoryMovementType;
use App\Livewire\ActivityLogTable;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\ProductStatus;
use App\PurchaseOrderStatus;
use App\Services\ActivityLogger;
use App\Services\InventoryService;
use App\Services\ProductService;
use App\Services\PurchaseOrderService;
use App\Services\ReceivingService;
use App\Services\StockAdjustmentService;
use App\Services\StockTransferService;
use App\StockTransferStatus;
use Database\Seeders\ActivityLogPermissionSeeder;
use Database\Seeders\PurchasingPermissionSeeder;
use Database\Seeders\StockAdjustmentPermissionSeeder;
use Database\Seeders\StockTransferPermissionSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Model::withoutEvents(function (): void {
        $this->seed([ActivityLogPermissionSeeder::class, PurchasingPermissionSeeder::class, StockAdjustmentPermissionSeeder::class, StockTransferPermissionSeeder::class]);
        $this->actor = User::factory()->create(['is_active' => true, 'password' => 'audit-password']);
        $this->actor->roles()->attach(Role::pluck('id'));
    });
    $this->stock = StockItem::factory()->create();
    $this->warehouse = Warehouse::factory()->create();
    DB::table('activity_logs')->delete();
});

test('successful login records actor IP and no credentials or request payload', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->post(route('login.store'), [
        'email' => $this->actor->email, 'password' => 'audit-password', 'secret' => 'DO-NOT-LOG',
    ])->assertRedirect();
    $log = ActivityLog::where('event', 'auth.login')->sole();
    expect($log->causer_id)->toBe($this->actor->id)->and($log->subject_id)->toBe($this->actor->id)
        ->and($log->ip_address)->toBe('192.0.2.10')->and($log->properties)->toBe(['old' => [], 'new' => []]);
    expect(json_encode(ActivityLog::all()->toArray()))->not->toContain('audit-password', 'DO-NOT-LOG', $this->actor->password);
});

test('failed or inactive login is never recorded as a successful login', function (bool $active) {
    Model::withoutEvents(fn () => $this->actor->update(['is_active' => $active]));
    $this->post(route('login.store'), ['email' => $this->actor->email, 'password' => $active ? 'wrong' : 'audit-password'])->assertSessionHasErrors();
    expect(ActivityLog::where('event', 'auth.login')->count())->toBe(0);
})->with([true, false]);

test('product creation changes and deactivation record only safe old and new values', function () {
    $this->actingAs($this->actor);
    $product = app(ProductService::class)->save([
        'name' => 'Audit product', 'description' => 'PRIVATE-NOTE', 'category_id' => $this->stock->product->category_id,
        'brand_id' => null, 'unit_id' => $this->stock->product->unit_id, 'sku' => 'AUDIT-SKU',
        'cost_price' => '10', 'selling_price' => '12', 'reorder_level' => '3', 'status' => 'active',
    ]);
    expect(ActivityLog::where('event', 'product.created')->sole()->causer_id)->toBe($this->actor->id);
    $product->update(['name' => 'Renamed product']);
    $updated = ActivityLog::where('event', 'product.updated')->sole();
    expect($updated->properties['old']['name'])->toBe('Audit product')->and($updated->properties['new']['name'])->toBe('Renamed product');
    app(ProductService::class)->changeStatus($product, ProductStatus::Inactive);
    expect(ActivityLog::where('event', 'product.deactivated')->sole()->properties['new']['status'])->toBe('inactive');
    expect(json_encode(ActivityLog::all()->toArray()))->not->toContain('PRIVATE-NOTE');
});

test('user and role changes exclude passwords tokens and email values', function () {
    $this->actingAs($this->actor);
    $user = User::factory()->create(['is_active' => true]);
    $user->update(['name' => 'Changed name', 'email' => 'private@example.test', 'password' => 'new-secret', 'remember_token' => 'secret-token']);
    $log = ActivityLog::where('event', 'user.updated')->where('subject_id', $user->id)->sole();
    expect($log->properties['new']['credentials_changed'])->toBeTrue()->and($log->properties['new']['email_changed'])->toBeTrue();
    $role = Role::first();
    $user->roles()->syncWithoutDetaching([$role->id]);
    $user->roles()->syncWithoutDetaching([$role->id]);
    $user->roles()->detach($role->id);
    expect(ActivityLog::where('event', 'user.role_assigned')->count())->toBe(1)
        ->and(ActivityLog::where('event', 'user.role_removed')->count())->toBe(1);
    expect(json_encode(ActivityLog::all()->toArray()))->not->toContain('new-secret', 'secret-token', 'private@example.test', $user->password);
});

test('purchase receiving and inventory use the explicit actor and deduplicate receipt retries', function () {
    $other = Model::withoutEvents(fn () => User::factory()->create());
    $this->actingAs($other);
    $service = app(PurchaseOrderService::class);
    $order = $service->saveDraft([
        'supplier_id' => Supplier::factory()->create()->id, 'warehouse_id' => $this->warehouse->id,
        'ordered_at' => today()->toDateString(), 'notes' => 'SECRET-NOTE',
        'items' => [['stock_item_id' => $this->stock->id, 'ordered_quantity' => '10', 'unit_cost' => '2', 'discount' => '0', 'tax' => '0']],
    ], $this->actor);
    $service->transition($order, PurchaseOrderStatus::Ordered, $this->actor);
    $data = ['idempotency_key' => (string) Str::uuid(), 'notes' => 'SECRET-NOTE',
        'items' => [['purchase_order_item_id' => $order->items()->sole()->id, 'quantity' => '5']]];
    app(ReceivingService::class)->receive($order, $data, $this->actor);
    $count = ActivityLog::count();
    app(ReceivingService::class)->receive($order, $data, $this->actor);
    expect(ActivityLog::count())->toBe($count)
        ->and(ActivityLog::where('event', 'purchase_order.created')->sole()->causer_id)->toBe($this->actor->id)
        ->and(ActivityLog::where('event', 'purchase.received')->sole()->causer_id)->toBe($this->actor->id);
    $movement = ActivityLog::where('event', 'inventory.changed')->sole();
    expect($movement->causer_id)->toBe($this->actor->id)->and($movement->properties['old']['quantity'])->toBe('0.0000')
        ->and($movement->properties['new']['quantity'])->toBe('5.0000');
    expect(json_encode(ActivityLog::all()->toArray()))->not->toContain('SECRET-NOTE', $data['idempotency_key']);
    $this->stock->product->update(['name' => 'After actor scope']);
    expect(ActivityLog::where('event', 'product.updated')->sole()->causer_id)->toBe($other->id);
});

test('adjustment and completed transfer audit all linked stock changes', function () {
    app(InventoryService::class)->increaseStock($this->stock, $this->warehouse, '10', InventoryMovementType::Purchase, $this->actor);
    app(StockAdjustmentService::class)->adjust([
        'stock_item_id' => $this->stock->id, 'warehouse_id' => $this->warehouse->id, 'expected_quantity' => '10',
        'new_quantity' => '9', 'reason' => 'PRIVATE-REASON', 'idempotency_key' => (string) Str::uuid(),
    ], $this->actor);
    $transfer = app(StockTransferService::class)->saveDraft([
        'source_warehouse_id' => $this->warehouse->id, 'destination_warehouse_id' => Warehouse::factory()->create()->id,
        'transfer_date' => today()->toDateString(), 'items' => [['stock_item_id' => $this->stock->id, 'quantity' => '3']],
    ], $this->actor);
    app(StockTransferService::class)->transition($transfer, StockTransferStatus::Completed, $this->actor, 1);
    expect(ActivityLog::where('event', 'stock_adjustment.created')->sole()->causer_id)->toBe($this->actor->id)
        ->and(ActivityLog::where('event', 'stock_transfer.updated')->sole()->properties['new']['status'])->toBe('completed')
        ->and(ActivityLog::where('event', 'inventory.changed')->count())->toBe(4);
    $count = ActivityLog::count();
    app(StockTransferService::class)->transition($transfer, StockTransferStatus::Completed, $this->actor, 1);
    expect(ActivityLog::count())->toBe($count);
    expect(json_encode(ActivityLog::all()->toArray()))->not->toContain('PRIVATE-REASON');
});

test('outer transaction rollback removes audit entries and does not leak actor context', function () {
    expect(fn () => app(ActivityLogger::class)->transaction($this->actor, function (): void {
        app(InventoryService::class)->increaseStock($this->stock, $this->warehouse, '5', InventoryMovementType::Purchase, $this->actor);
        throw new RuntimeException('Rollback');
    }))->toThrow(RuntimeException::class, 'Rollback');
    $this->assertDatabaseCount('activity_logs', 0);
    $this->assertDatabaseCount('inventory_movements', 0);
    $this->assertDatabaseCount('inventories', 0);
    $this->stock->product->update(['name' => 'System update']);
    expect(ActivityLog::sole()->causer_id)->toBeNull();
});

test('audit insertion failure rolls back user changes and inventory changes', function () {
    ActivityLog::creating(function (): void {
        throw new RuntimeException('Audit failed');
    });
    try {
        expect(fn () => $this->actor->update(['name' => 'Must rollback']))->toThrow(RuntimeException::class, 'Audit failed');
        expect(fn () => app(InventoryService::class)->increaseStock($this->stock, $this->warehouse, '1', InventoryMovementType::Purchase, $this->actor))->toThrow(RuntimeException::class, 'Audit failed');
    } finally {
        Event::forget('eloquent.creating: '.ActivityLog::class);
    }
    expect($this->actor->fresh()->name)->not->toBe('Must rollback');
    $this->assertDatabaseCount('inventories', 0);
    $this->assertDatabaseCount('inventory_movements', 0);
});

test('logger filters nested and sensitive fields and logs are immutable', function () {
    $log = app(ActivityLogger::class)->record('user.updated', $this->actor,
        ['password' => 'SECRET', 'name' => 'Before', 'metadata' => ['token' => 'SECRET']],
        ['name' => ['password' => 'SECRET'], 'password' => 'SECRET', 'remember_token' => 'SECRET']);
    expect($log->properties)->toBe(['old' => ['name' => 'Before'], 'new' => []]);
    expect(fn () => $log->forceFill(['event' => 'tampered'])->save())->toThrow(LogicException::class);
    expect(fn () => $log->delete())->toThrow(LogicException::class);
});

test('activity admin page authorizes filters paginates and escapes values', function () {
    ActivityLog::factory()->count(30)->create(['causer_id' => $this->actor->id, 'created_at' => '2026-09-20 23:59:59']);
    ActivityLog::factory()->create(['event' => 'user.updated', 'created_at' => '2026-09-21 00:00:00',
        'properties' => ['old' => [], 'new' => ['name' => '<script>alert(1)</script>']]]);
    $this->get(route('activity-logs.index'))->assertRedirect(route('login'));
    $outsider = Model::withoutEvents(fn () => User::factory()->create());
    $this->actingAs($outsider)->get(route('activity-logs.index'))->assertForbidden();
    $this->actingAs($this->actor)->get(route('activity-logs.index'))->assertOk();
    Livewire::test(ActivityLogTable::class)->assertViewHas('logs', fn ($logs) => $logs->count() === 25 && $logs->total() === 31)
        ->assertDontSeeHtml('<script>alert(1)</script>')->call('setPage', 2)
        ->set('action', 'product.updated')->set('user', (string) $this->actor->id)
        ->set('from', '2026-09-20')->set('to', '2026-09-20')->assertViewHas('logs', fn ($logs) => $logs->currentPage() === 1 && $logs->total() === 30)
        ->set('search', 'Updated name')->assertViewHas('logs', fn ($logs) => $logs->total() === 30)
        ->set('to', 'invalid')->assertHasErrors('to')->call('clearFilters')->assertHasNoErrors();
    $this->post(route('activity-logs.index'))->assertStatus(405);
    $this->actor->roles()->detach();
    Livewire::test(ActivityLogTable::class)->assertForbidden();
});

test('activity permission grants are idempotent and audited', function () {
    $target = Model::withoutEvents(fn () => User::factory()->create(['is_active' => true]));
    $this->artisan('activity:grant', ['email' => $target->email])->assertSuccessful();
    $this->artisan('activity:grant', ['email' => $target->email])->assertSuccessful();
    expect(ActivityLog::where('event', 'user.role_assigned')->count())->toBe(1);
    $this->artisan('activity:grant', ['email' => 'missing@example.test'])->assertFailed();
});
