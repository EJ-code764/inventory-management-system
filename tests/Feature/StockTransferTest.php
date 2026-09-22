<?php

use App\InventoryMovementType;
use App\Livewire\StockTransferForm;
use App\Livewire\StockTransferTable;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockTransferService;
use App\StockTransferStatus;
use Database\Seeders\StockTransferPermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StockTransferPermissionSeeder::class);
    $this->actor = User::factory()->create(['is_active' => true]);
    $this->actor->roles()->attach(Role::where('slug', 'stock-transfer-manager')->sole());
    $this->source = Warehouse::factory()->create();
    $this->destination = Warehouse::factory()->create();
    $this->stock = StockItem::factory()->create(['sku' => 'TRANSFER-SKU']);
    $this->inventory = Inventory::factory()->create(['stock_item_id' => $this->stock->id, 'warehouse_id' => $this->source->id, 'quantity' => '10', 'reserved_quantity' => '2']);
    $this->data = ['source_warehouse_id' => $this->source->id, 'destination_warehouse_id' => $this->destination->id, 'transfer_date' => '2026-09-20', 'remarks' => 'Branch replenishment', 'items' => [['stock_item_id' => $this->stock->id, 'quantity' => '3.5001']]];
    $this->service = app(StockTransferService::class);
});

test('drafts can be revised without changing or reserving stock', function () {
    $transfer = $this->service->saveDraft([...$this->data, 'created_by' => 999, 'status' => 'completed'], $this->actor);
    expect($transfer->status)->toBe(StockTransferStatus::Draft)->and($transfer->created_by)->toBe($this->actor->id)
        ->and($transfer->number)->toStartWith('TR-')->and($transfer->items()->sole()->quantity)->toBe('3.5001');
    $updated = $this->service->saveDraft([...$this->data, 'revision' => 1, 'remarks' => 'Updated'], $this->actor, $transfer);
    expect($updated->revision)->toBe(2)->and($this->inventory->fresh()->quantity)->toBe('10.0000')
        ->and($this->inventory->fresh()->reserved_quantity)->toBe('2.0000');
    $this->assertDatabaseCount('inventory_movements', 0);
});

test('completion creates exact paired ledger entries and safely accepts retries', function () {
    $transfer = $this->service->saveDraft($this->data, $this->actor);
    $completed = $this->service->transition($transfer, StockTransferStatus::Completed, $this->actor, 1);
    expect($completed->status)->toBe(StockTransferStatus::Completed)->and($completed->processed_by)->toBe($this->actor->id)
        ->and($completed->completed_at)->not->toBeNull()->and($this->inventory->fresh()->quantity)->toBe('6.4999')
        ->and($this->inventory->fresh()->reserved_quantity)->toBe('2.0000')
        ->and(Inventory::where('warehouse_id', $this->destination->id)->sole()->quantity)->toBe('3.5001');
    $movements = $completed->movements()->orderBy('id')->get();
    expect($movements)->toHaveCount(2)->and($movements[0]->type)->toBe(InventoryMovementType::TransferOut)
        ->and($movements[0]->quantity_delta)->toBe('-3.5001')->and($movements[1]->type)->toBe(InventoryMovementType::TransferIn)
        ->and($movements[1]->quantity_delta)->toBe('3.5001');
    foreach ($movements as $movement) {
        expect($movement->performed_by)->toBe($this->actor->id)->and($movement->reference_id)->toBe($transfer->id)
            ->and($movement->reference_type)->toBe(StockTransfer::class)->and($movement->occurred_at)->not->toBeNull();
    }
    $this->service->transition($transfer, StockTransferStatus::Completed, $this->actor, 1);
    $this->assertDatabaseCount('inventory_movements', 2);
});

test('insufficient available stock leaves a draft and balances untouched', function (string $quantity) {
    $transfer = $this->service->saveDraft([...$this->data, 'items' => [['stock_item_id' => $this->stock->id, 'quantity' => $quantity]]], $this->actor);
    expect(fn () => $this->service->transition($transfer, StockTransferStatus::Completed, $this->actor, 1))->toThrow(ValidationException::class);
    expect($transfer->fresh()->status)->toBe(StockTransferStatus::Draft)->and($this->inventory->fresh()->quantity)->toBe('10.0000');
    $this->assertDatabaseCount('inventory_movements', 0);
})->with(['8.0001', '11']);

test('same missing and inactive warehouses are rejected', function (string $case) {
    $data = $this->data;
    if ($case === 'same') {
        $data['destination_warehouse_id'] = $this->source->id;
    } elseif ($case === 'missing') {
        $data['source_warehouse_id'] = 99999;
    } else {
        $this->destination->update(['status' => 'inactive']);
    }
    expect(fn () => $this->service->saveDraft($data, $this->actor))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('stock_transfers', 0);
})->with(['same', 'missing', 'inactive']);

test('completion revalidates active warehouses and products', function (string $target) {
    $transfer = $this->service->saveDraft($this->data, $this->actor);
    match ($target) {
        'warehouse' => $this->destination->update(['status' => 'inactive']),
        'product' => $this->stock->product->update(['status' => 'inactive']),
        'stock' => $this->stock->update(['is_active' => false]),
    };
    expect(fn () => $this->service->transition($transfer, StockTransferStatus::Completed, $this->actor, 1))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('inventory_movements', 0);
})->with(['warehouse', 'product', 'stock']);

test('invalid transfer line quantities are rejected', function (mixed $quantity) {
    $data = [...$this->data, 'items' => [['stock_item_id' => $this->stock->id, 'quantity' => $quantity]]];
    expect(fn () => $this->service->saveDraft($data, $this->actor))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('stock_transfers', 0);
})->with(['0', '-1', '1.00001', '1e3', 'abc', '', '10000000000000000']);

test('empty and duplicate transfer lines are rejected', function () {
    foreach ([[], [$this->data['items'][0], $this->data['items'][0]]] as $items) {
        expect(fn () => $this->service->saveDraft([...$this->data, 'items' => $items], $this->actor))->toThrow(ValidationException::class);
    }
});

test('destination ledger failure rolls back both balances and transfer status', function () {
    $transfer = $this->service->saveDraft($this->data, $this->actor);
    InventoryMovement::creating(function (InventoryMovement $movement): void {
        if ($movement->type === InventoryMovementType::TransferIn) {
            throw new RuntimeException('Simulated ledger failure');
        }
    });
    try {
        expect(fn () => $this->service->transition($transfer, StockTransferStatus::Completed, $this->actor, 1))->toThrow(RuntimeException::class, 'Simulated ledger failure');
    } finally {
        Event::forget('eloquent.creating: '.InventoryMovement::class);
        InventoryMovement::clearBootedModels();
    }
    expect($this->inventory->fresh()->quantity)->toBe('10.0000')->and($transfer->fresh()->status)->toBe(StockTransferStatus::Draft)
        ->and($transfer->fresh()->processed_by)->toBeNull();
    $this->assertDatabaseCount('inventories', 1);
    $this->assertDatabaseCount('inventory_movements', 0);
});

test('cancellation and stale revisions cannot change inventory', function () {
    $transfer = $this->service->saveDraft($this->data, $this->actor);
    $this->service->saveDraft([...$this->data, 'revision' => 1], $this->actor, $transfer);
    expect(fn () => $this->service->transition($transfer, StockTransferStatus::Completed, $this->actor, 1))->toThrow(ValidationException::class);
    expect(fn () => $this->service->saveDraft([...$this->data, 'revision' => 1], $this->actor, $transfer))->toThrow(ValidationException::class);
    $cancelled = $this->service->transition($transfer, StockTransferStatus::Cancelled, $this->actor, 2);
    expect($cancelled->status)->toBe(StockTransferStatus::Cancelled);
    expect(fn () => $this->service->transition($transfer, StockTransferStatus::Completed, $this->actor, 3))->toThrow(ValidationException::class);
    expect(fn () => $cancelled->forceFill(['remarks' => 'tampered'])->save())->toThrow(LogicException::class);
    expect(fn () => $cancelled->items()->sole()->forceFill(['quantity' => '1'])->save())->toThrow(LogicException::class);
    $this->assertDatabaseCount('inventory_movements', 0);
});

test('routes and livewire render and validate transfer workflows', function () {
    $this->get(route('stock-transfers.index'))->assertRedirect(route('login'));
    $this->actingAs($this->actor);
    $transfer = $this->service->saveDraft($this->data, $this->actor);
    foreach (['index' => [], 'create' => [], 'show' => $transfer, 'edit' => $transfer] as $route => $parameters) {
        $this->get(route('stock-transfers.'.$route, $parameters))->assertOk();
    }
    Livewire::test(StockTransferTable::class)->set('search', 'TRANSFER-SKU')->assertSee($transfer->number)
        ->set('destination', $this->source->id)->assertDontSee($transfer->number);
    Livewire::test(StockTransferForm::class)->call('addItem', $this->stock->id)
        ->set('form.source_warehouse_id', $this->source->id)->set('form.destination_warehouse_id', $this->source->id)
        ->call('save')->assertHasErrors('form.destination_warehouse_id')
        ->set('form.destination_warehouse_id', $this->destination->id)->call('save')->assertHasNoErrors()->assertRedirect();
    $this->assertDatabaseCount('inventory_movements', 0);
    $this->patch(route('stock-transfers.status', $transfer), ['status' => 'completed', 'revision' => 1])->assertRedirect();
    $this->get(route('stock-transfers.show', $transfer))->assertOk()->assertSee('TRANSFER_OUT')->assertSee('TRANSFER_IN');
});

test('unauthorized users cannot view or mutate transfers', function () {
    $outsider = User::factory()->create(['is_active' => true]);
    expect(fn () => $this->service->saveDraft($this->data, $outsider))->toThrow(AuthorizationException::class);
    $transfer = $this->service->saveDraft($this->data, $this->actor);
    $this->actingAs($outsider)->get(route('stock-transfers.index'))->assertForbidden();
    $this->patch(route('stock-transfers.status', $transfer), ['status' => 'completed', 'revision' => 1])->assertForbidden();
    $this->assertDatabaseCount('inventory_movements', 0);
});

test('multi item transfers include variants and preserve unrelated warehouses', function () {
    $variant = ProductVariant::factory()->create();
    $stock = StockItem::factory()->create(['product_id' => null, 'product_variant_id' => $variant->id]);
    Inventory::factory()->create(['stock_item_id' => $stock->id, 'warehouse_id' => $this->source->id, 'quantity' => '4', 'reserved_quantity' => '0']);
    $other = Inventory::factory()->create(['stock_item_id' => $stock->id, 'quantity' => '9']);
    $transfer = $this->service->saveDraft([...$this->data, 'items' => [...$this->data['items'], ['stock_item_id' => $stock->id, 'quantity' => 1.2345]]], $this->actor);
    $this->service->transition($transfer, StockTransferStatus::Completed, $this->actor, 1);
    expect($transfer->movements()->count())->toBe(4)
        ->and(Inventory::where('stock_item_id', $stock->id)->where('warehouse_id', $this->source->id)->sole()->quantity)->toBe('2.7655')
        ->and(Inventory::where('stock_item_id', $stock->id)->where('warehouse_id', $this->destination->id)->sole()->quantity)->toBe('1.2345')
        ->and($other->fresh()->quantity)->toBe('9.0000');
});

test('failure on a later transfer item rolls back earlier completed pairs', function () {
    $stock = StockItem::factory()->create();
    Inventory::factory()->create(['stock_item_id' => $stock->id, 'warehouse_id' => $this->source->id, 'quantity' => '4', 'reserved_quantity' => '0']);
    $transfer = $this->service->saveDraft([...$this->data, 'items' => [...$this->data['items'], ['stock_item_id' => $stock->id, 'quantity' => '1']]], $this->actor);
    InventoryMovement::creating(function (InventoryMovement $movement) use ($stock): void {
        if ($movement->stock_item_id === $stock->id) {
            throw new RuntimeException('Later item failure');
        }
    });
    try {
        expect(fn () => $this->service->transition($transfer, StockTransferStatus::Completed, $this->actor, 1))->toThrow(RuntimeException::class, 'Later item failure');
    } finally {
        Event::forget('eloquent.creating: '.InventoryMovement::class);
        InventoryMovement::clearBootedModels();
    }
    expect($this->inventory->fresh()->quantity)->toBe('10.0000')->and($transfer->fresh()->status)->toBe(StockTransferStatus::Draft);
    $this->assertDatabaseCount('inventory_movements', 0);
    $this->assertDatabaseCount('inventories', 2);
});

test('transfer permission seed and grant are idempotent and do not create users', function () {
    $this->seed(StockTransferPermissionSeeder::class);
    $this->artisan('transfers:grant', ['email' => $this->actor->email])->assertSuccessful();
    $this->artisan('transfers:grant', ['email' => 'missing@example.test'])->assertFailed();
    expect($this->actor->roles()->count())->toBe(1);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('permissions', 5);
});

test('transfer schema upgrade preserves legacy headers and supports rollback', function () {
    $migration = require database_path('migrations/2026_09_20_005436_extend_stock_transfers_for_management.php');
    $migration->down();
    DB::table('stock_transfers')->insert([
        'number' => 'LEGACY-TRANSFER', 'source_warehouse_id' => $this->source->id, 'destination_warehouse_id' => $this->destination->id,
        'requested_by' => $this->actor->id, 'notes' => 'Legacy remarks', 'status' => 'draft', 'created_at' => '2026-09-19 10:00:00',
    ]);
    $migration->up();
    $transfer = StockTransfer::where('number', 'LEGACY-TRANSFER')->sole();
    expect($transfer->created_by)->toBe($this->actor->id)->and($transfer->remarks)->toBe('Legacy remarks')
        ->and($transfer->transfer_date->format('Y-m-d'))->toBe('2026-09-19')->and($transfer->revision)->toBe(1);
});
