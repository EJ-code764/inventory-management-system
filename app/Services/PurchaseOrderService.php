<?php

namespace App\Services;

use App\Http\Requests\PurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\ProductStatus;
use App\PurchaseOrderStatus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    /** @param array<string, mixed> $data */
    public function saveDraft(array $data, User $actor, ?PurchaseOrder $order = null): PurchaseOrder
    {
        Gate::forUser($actor)->authorize($order ? 'update' : 'create', $order ?? PurchaseOrder::class);
        $data = Validator::make($data, (new PurchaseOrderRequest)->rules())->validate();
        $totals = app(PurchaseTotals::class)->calculate($data['items']);

        return app(ActivityLogger::class)->transaction($actor, function () use ($data, $actor, $order, $totals): PurchaseOrder {
            if ($order) {
                $order = PurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
                $this->requireStatus($order, [PurchaseOrderStatus::Draft]);
                if ((int) ($data['revision'] ?? 0) !== $order->revision) {
                    throw ValidationException::withMessages(['revision' => 'This draft has changed. Reload it before saving.']);
                }
            } else {
                $order = new PurchaseOrder;
                $order->forceFill(['number' => 'PO-'.Str::ulid(), 'created_by' => $actor->id, 'status' => PurchaseOrderStatus::Draft, 'revision' => 0]);
            }
            $this->validateParties((int) $data['supplier_id'], (int) $data['warehouse_id']);
            $ids = array_column($data['items'], 'stock_item_id');
            sort($ids, SORT_NUMERIC);
            foreach ($ids as $id) {
                $this->activeStockItem((int) $id);
            }
            $order->forceFill([
                'supplier_id' => $data['supplier_id'], 'warehouse_id' => $data['warehouse_id'],
                'ordered_at' => $data['ordered_at'], 'expected_at' => $data['expected_at'] ?? null,
                'notes' => $data['notes'] ?? null, 'revision' => $order->revision + 1,
                'subtotal' => $totals['subtotal'], 'discount' => $totals['discount'], 'tax' => $totals['tax'], 'total' => $totals['total'],
            ])->save();
            $order->items()->get()->each->delete();
            foreach ($totals['items'] as $line) {
                PurchaseOrderItem::forceCreate([...$line, 'purchase_order_id' => $order->id, 'received_quantity' => '0.0000']);
            }

            return $order->refresh();
        }, 3);
    }

    public function transition(PurchaseOrder $order, PurchaseOrderStatus $status, User $actor): PurchaseOrder
    {
        if (! in_array($status, [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::Cancelled], true)) {
            throw ValidationException::withMessages(['status' => 'Receiving alone determines received statuses.']);
        }
        Gate::forUser($actor)->authorize($status === PurchaseOrderStatus::Ordered ? 'order' : 'cancel', $order);

        return app(ActivityLogger::class)->transaction($actor, function () use ($order, $status, $actor): PurchaseOrder {
            $order = PurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->requireStatus($order, $status === PurchaseOrderStatus::Ordered ? [PurchaseOrderStatus::Draft] : [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Ordered]);
            if ($order->receipts()->exists() || $order->items()->where('received_quantity', '>', 0)->exists()) {
                throw ValidationException::withMessages(['status' => 'An order with receipts cannot be cancelled or reordered.']);
            }
            if ($status === PurchaseOrderStatus::Ordered) {
                $this->validateParties($order->supplier_id, $order->warehouse_id);
                $items = $order->items()->orderBy('stock_item_id')->get();
                if ($items->isEmpty()) {
                    throw ValidationException::withMessages(['items' => 'Add at least one item before ordering.']);
                }
                Validator::make([
                    'supplier_id' => $order->supplier_id, 'warehouse_id' => $order->warehouse_id,
                    'ordered_at' => $order->ordered_at?->format('Y-m-d'), 'expected_at' => $order->expected_at?->format('Y-m-d'),
                    'items' => $items->map->only(['stock_item_id', 'ordered_quantity', 'unit_cost', 'discount', 'tax'])->all(),
                ], (new PurchaseOrderRequest)->rules())->validate();
                foreach ($items as $item) {
                    $this->activeStockItem($item->stock_item_id);
                }
                $order->forceFill(['approved_by' => $actor->id]);
            }
            $order->forceFill(['status' => $status, 'revision' => $order->revision + 1])->save();

            return $order->refresh();
        }, 3);
    }

    /** @param array<PurchaseOrderStatus> $allowed */
    public function requireStatus(PurchaseOrder $order, array $allowed): void
    {
        if (! in_array($order->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => 'This action is not allowed for the current purchase order status.']);
        }
    }

    public function validateParties(int $supplierId, int $warehouseId): void
    {
        if (! Supplier::whereKey($supplierId)->where('status', 'active')->sharedLock()->exists()) {
            throw ValidationException::withMessages(['supplier_id' => 'Select an active supplier.']);
        }
        if (! Warehouse::whereKey($warehouseId)->where('is_active', true)->sharedLock()->exists()) {
            throw ValidationException::withMessages(['warehouse_id' => 'Select an active warehouse.']);
        }
    }

    /** Lock stock identities in ascending order when processing multiple lines. */
    public function activeStockItem(int $id): StockItem
    {
        $item = StockItem::whereKey($id)->lockForUpdate()->first();
        $product = $item?->product ?? $item?->variant?->product;
        if (! $item || ! $item->is_active || ! $product || $product->status !== ProductStatus::Active
            || (($item->product_id === null) === ($item->product_variant_id === null))
            || ($item->product_id !== null && $product->has_variants) || ($item->variant && ! $item->variant->is_active)) {
            throw ValidationException::withMessages(['items' => 'Select an active sellable product or variant.']);
        }

        return $item;
    }
}
