<?php

namespace App\Services;

use App\Http\Requests\StockTransferRequest;
use App\Models\Inventory;
use App\Models\StockItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Models\Warehouse;
use App\ProductStatus;
use App\StockTransferStatus;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    /** @param array<string, mixed> $data */
    public function saveDraft(array $data, User $actor, ?StockTransfer $transfer = null): StockTransfer
    {
        Gate::forUser($actor)->authorize($transfer ? 'update' : 'create', $transfer ?? StockTransfer::class);
        $data = Validator::make($data, (new StockTransferRequest)->rules())->validate();

        return app(ActivityLogger::class)->transaction($actor, function () use ($data, $actor, $transfer): StockTransfer {
            $this->validateActor($actor);
            if ($transfer) {
                $transfer = StockTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();
                $this->requireDraft($transfer, (int) ($data['revision'] ?? 0));
            } else {
                $transfer = new StockTransfer;
                $transfer->forceFill(['number' => 'TR-'.Str::ulid(), 'created_by' => $actor->id, 'status' => StockTransferStatus::Draft, 'revision' => 0]);
            }
            $this->warehouses((int) $data['source_warehouse_id'], (int) $data['destination_warehouse_id']);
            $ids = array_map('intval', array_column($data['items'], 'stock_item_id'));
            $this->stockItems($ids);
            $transfer->forceFill([
                'source_warehouse_id' => $data['source_warehouse_id'], 'destination_warehouse_id' => $data['destination_warehouse_id'],
                'transfer_date' => $data['transfer_date'], 'remarks' => $data['remarks'] ?? null, 'revision' => $transfer->revision + 1,
            ])->save();
            $transfer->items()->get()->each->delete();
            foreach ($data['items'] as $line) {
                StockTransferItem::forceCreate([
                    'stock_transfer_id' => $transfer->id, 'stock_item_id' => $line['stock_item_id'],
                    'quantity' => (string) BigDecimal::of((string) $line['quantity'])->toScale(4),
                ]);
            }

            return $transfer->refresh();
        }, 3);
    }

    public function transition(StockTransfer $transfer, StockTransferStatus $status, User $actor, int $revision): StockTransfer
    {
        if (! in_array($status, [StockTransferStatus::Completed, StockTransferStatus::Cancelled], true)) {
            throw ValidationException::withMessages(['status' => 'A draft may only be completed or cancelled.']);
        }
        Gate::forUser($actor)->authorize($status === StockTransferStatus::Completed ? 'complete' : 'cancel', $transfer);

        return app(ActivityLogger::class)->transaction($actor, function () use ($transfer, $status, $actor, $revision): StockTransfer {
            $this->validateActor($actor);
            $transfer = StockTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if ($transfer->status === $status) {
                return $transfer;
            }
            $this->requireDraft($transfer, $revision);
            if ($status === StockTransferStatus::Completed) {
                $warehouses = $this->warehouses($transfer->source_warehouse_id, $transfer->destination_warehouse_id);
                $items = $transfer->items()->orderBy('stock_item_id')->lockForUpdate()->get();
                Validator::make([
                    'source_warehouse_id' => $transfer->source_warehouse_id, 'destination_warehouse_id' => $transfer->destination_warehouse_id,
                    'transfer_date' => $transfer->transfer_date?->format('Y-m-d'),
                    'items' => $items->map->only(['stock_item_id', 'quantity'])->all(),
                ], (new StockTransferRequest)->rules())->validate();
                $stockItems = $this->stockItems($items->pluck('stock_item_id')->all());
                foreach ($items as $item) {
                    $inventory = Inventory::where('stock_item_id', $item->stock_item_id)->where('warehouse_id', $transfer->source_warehouse_id)->lockForUpdate()->first();
                    if (! $inventory || BigDecimal::of($inventory->availableQuantity())->isLessThan($item->quantity)) {
                        throw ValidationException::withMessages(['items' => 'Insufficient available stock for SKU '.$stockItems->get($item->stock_item_id)->sku.'. Reserved stock cannot be transferred.']);
                    }
                }
                foreach ($items as $item) {
                    app(InventoryService::class)->transfer(
                        $stockItems->get($item->stock_item_id), $warehouses->get($transfer->source_warehouse_id),
                        $warehouses->get($transfer->destination_warehouse_id), $item->quantity, $actor, $transfer,
                    );
                }
                $transfer->forceFill(['completed_at' => now()]);
            }
            $transfer->forceFill(['status' => $status, 'processed_by' => $actor->id, 'revision' => $transfer->revision + 1])->save();

            return $transfer->refresh();
        }, 3);
    }

    private function validateActor(User $actor): void
    {
        if (! User::whereKey($actor->id)->where('is_active', true)->sharedLock()->exists()) {
            throw ValidationException::withMessages(['actor' => 'An active responsible user is required.']);
        }
    }

    private function requireDraft(StockTransfer $transfer, int $revision): void
    {
        if ($transfer->status !== StockTransferStatus::Draft) {
            throw ValidationException::withMessages(['status' => 'Only draft transfers can be changed.']);
        }
        if ($revision !== $transfer->revision) {
            throw ValidationException::withMessages(['revision' => 'This transfer changed. Reload it and review the latest items before continuing.']);
        }
    }

    /** @return Collection<int, Warehouse> */
    private function warehouses(int $source, int $destination): Collection
    {
        if ($source === $destination) {
            throw ValidationException::withMessages(['destination_warehouse_id' => 'Source and destination warehouses must differ.']);
        }
        $warehouses = Warehouse::whereIn('id', [$source, $destination])->orderBy('id')->sharedLock()->get()->keyBy('id');
        foreach (['source_warehouse_id' => $source, 'destination_warehouse_id' => $destination] as $field => $id) {
            if (! $warehouses->has($id) || ! $warehouses->get($id)->is_active) {
                throw ValidationException::withMessages([$field => 'Select an active warehouse.']);
            }
        }

        return $warehouses;
    }

    /** Lock stable parents in a consistent order, including when destination balances do not exist.
     * @param  array<int>  $ids
     * @return Collection<int, StockItem>
     */
    private function stockItems(array $ids): Collection
    {
        $items = StockItem::with(['product', 'variant.product'])->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if ($items->count() !== count($ids)) {
            throw ValidationException::withMessages(['items' => 'Select distinct valid stock items.']);
        }
        foreach ($items as $item) {
            $product = $item->product ?? $item->variant?->product;
            if (! $item->is_active || ! $product || $product->status !== ProductStatus::Active
                || (($item->product_id === null) === ($item->product_variant_id === null))
                || ($item->product_id !== null && $product->has_variants) || ($item->variant && ! $item->variant->is_active)) {
                throw ValidationException::withMessages(['items' => 'Select active sellable products or variants.']);
            }
        }

        return $items;
    }
}
