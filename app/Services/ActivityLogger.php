<?php

namespace App\Services;

use App\Models;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ActivityLogger
{
    /** Values are allowlisted; credentials, tokens, arbitrary JSON and free text are excluded. */
    public const FIELDS = [
        Models\Product::class => ['name', 'category_id', 'brand_id', 'unit_id', 'status', 'has_variants'],
        Models\StockItem::class => ['product_id', 'product_variant_id', 'sku', 'barcode', 'cost_price', 'selling_price', 'reorder_level', 'is_active'],
        Models\User::class => ['name', 'is_active', 'email_changed', 'credentials_changed', 'role_id'],
        Models\PurchaseOrder::class => ['number', 'supplier_id', 'warehouse_id', 'created_by', 'approved_by', 'status', 'ordered_at', 'expected_at', 'subtotal', 'discount', 'tax', 'total', 'revision'],
        Models\PurchaseOrderItem::class => ['purchase_order_id', 'stock_item_id', 'ordered_quantity', 'received_quantity', 'unit_cost', 'discount', 'tax', 'subtotal'],
        Models\PurchaseReceipt::class => ['number', 'purchase_order_id', 'warehouse_id', 'received_by', 'received_at'],
        Models\PurchaseReceiptItem::class => ['purchase_receipt_id', 'purchase_order_item_id', 'stock_item_id', 'quantity', 'unit_cost', 'batch_number', 'expiration_date'],
        Models\StockAdjustment::class => ['number', 'warehouse_id', 'created_by', 'status'],
        Models\StockAdjustmentItem::class => ['stock_adjustment_id', 'stock_item_id', 'previous_quantity', 'new_quantity', 'difference', 'quantity'],
        Models\StockTransfer::class => ['number', 'source_warehouse_id', 'destination_warehouse_id', 'created_by', 'processed_by', 'status', 'transfer_date', 'completed_at', 'revision'],
        Models\StockTransferItem::class => ['stock_transfer_id', 'stock_item_id', 'quantity'],
        Models\InventoryMovement::class => ['stock_item_id', 'warehouse_id', 'product_batch_id', 'performed_by', 'type', 'quantity', 'quantity_delta', 'quantity_before', 'quantity_after', 'unit_cost', 'reference_type', 'reference_id', 'occurred_at'],
    ];

    private ?Models\User $actor = null;

    /** Scope the explicit domain actor through nested model events and retryable transactions. */
    public function transaction(Models\User $actor, Closure $callback, int $attempts = 3): mixed
    {
        $previous = $this->actor;
        $this->actor = $actor;
        try {
            return DB::transaction($callback, $attempts);
        } finally {
            $this->actor = $previous;
        }
    }

    /** @param array<string, mixed> $old
     * @param  array<string, mixed>  $new
     */
    public function record(string $event, Model $subject, array $old = [], array $new = [], ?Models\User $actor = null): Models\ActivityLog
    {
        $allowed = self::FIELDS[$subject::class] ?? [];
        $sanitize = static fn (array $values): array => array_filter(Arr::only($values, $allowed), static fn (mixed $value): bool => is_scalar($value) || $value === null);
        $actor ??= $this->actor ?? auth()->user();
        $request = app()->bound('request') ? request() : null;
        $ip = $request?->route() ? $request->ip() : null;

        return Models\ActivityLog::forceCreate([
            'causer_id' => $actor && Models\User::whereKey($actor->id)->exists() ? $actor->id : null,
            'event' => $event, 'description' => $event,
            'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(),
            'properties' => ['old' => $sanitize($old), 'new' => $sanitize($new)],
            'ip_address' => filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null,
        ]);
    }
}
