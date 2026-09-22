<?php

namespace App\Services;

use App\Http\Requests\PurchaseReceiptRequest;
use App\InventoryMovementType;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\User;
use App\PurchaseOrderStatus;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReceivingService
{
    /** @param array<string, mixed> $data */
    public function receive(PurchaseOrder $order, array $data, User $actor): PurchaseReceipt
    {
        Gate::forUser($actor)->authorize('receive', $order);
        $data = Validator::make($data, (new PurchaseReceiptRequest)->rules())->validate();
        $lines = collect($data['items'])->map(fn (array $line): array => [
            'purchase_order_item_id' => (int) $line['purchase_order_item_id'],
            'quantity' => (string) BigDecimal::of((string) $line['quantity'])->toScale(4),
            ...(isset($line['batch_number']) && trim($line['batch_number']) !== '' ? ['batch_number' => trim($line['batch_number']), 'expiration_date' => ($line['expiration_date'] ?? null) ?: null] : []),
        ])->sortBy('purchase_order_item_id')->values()->all();
        $hash = hash('sha256', json_encode([$order->id, $lines, $data['notes'] ?? null], JSON_THROW_ON_ERROR));

        return app(ActivityLogger::class)->transaction($actor, function () use ($order, $data, $actor, $lines, $hash): PurchaseReceipt {
            $order = PurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $existing = PurchaseReceipt::where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
            if ($existing) {
                if ($existing->purchase_order_id !== $order->id || $existing->request_hash !== $hash || $existing->received_by !== $actor->id) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This receipt key has already been used for a different submission.']);
                }

                return $existing;
            }
            $orders = app(PurchaseOrderService::class);
            $orders->requireStatus($order, [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::PartiallyReceived]);
            $orders->validateParties($order->supplier_id, $order->warehouse_id);
            $items = $order->items()->orderBy('stock_item_id')->lockForUpdate()->get()->keyBy('id');
            $quantities = collect($lines)->keyBy('purchase_order_item_id');
            foreach ($lines as $line) {
                $item = $items->get($line['purchase_order_item_id']);
                if (! $item || BigDecimal::of($line['quantity'])->isGreaterThan($item->remainingQuantity())) {
                    throw ValidationException::withMessages(['items' => 'Received quantities must belong to this order and cannot exceed the remaining quantity.']);
                }
            }
            $receipt = PurchaseReceipt::forceCreate([
                'number' => 'PR-'.Str::ulid(), 'purchase_order_id' => $order->id, 'warehouse_id' => $order->warehouse_id,
                'received_by' => $actor->id, 'received_at' => now(), 'notes' => $data['notes'] ?? null,
                'idempotency_key' => $data['idempotency_key'], 'request_hash' => $hash,
            ]);
            foreach ($items as $item) {
                $line = $quantities->get($item->id);
                if (! $line) {
                    continue;
                }
                $stockItem = $orders->activeStockItem($item->stock_item_id);
                $receiptItem = PurchaseReceiptItem::forceCreate([
                    'purchase_receipt_id' => $receipt->id, 'purchase_order_item_id' => $item->id,
                    'stock_item_id' => $item->stock_item_id, 'quantity' => $line['quantity'], 'unit_cost' => $item->unit_cost,
                    'batch_number' => $line['batch_number'] ?? null, 'expiration_date' => $line['expiration_date'] ?? null,
                ]);
                app(InventoryService::class)->increaseStock(
                    $stockItem, $order->warehouse, $line['quantity'], InventoryMovementType::Purchase, $actor,
                    $receipt->getMorphClass(), $receipt->id, 'Purchase receipt '.$receipt->number, $item->unit_cost,
                    batch: isset($line['batch_number']) ? [
                        'batch_number' => $line['batch_number'], 'expiration_date' => $line['expiration_date'],
                        'purchase_receipt_item_id' => $receiptItem->id,
                    ] : null,
                );
                $item->forceFill(['received_quantity' => (string) BigDecimal::of($item->received_quantity)->plus($line['quantity'])])->save();
            }
            $complete = $items->every(fn (PurchaseOrderItem $item): bool => BigDecimal::of($item->remainingQuantity())->isZero());
            $order->forceFill(['status' => $complete ? PurchaseOrderStatus::Received : PurchaseOrderStatus::PartiallyReceived, 'revision' => $order->revision + 1])->save();

            return $receipt;
        }, 3);
    }
}
