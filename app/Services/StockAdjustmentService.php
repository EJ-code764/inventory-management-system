<?php

namespace App\Services;

use App\Http\Requests\StockAdjustmentRequest;
use App\Models\Inventory;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockItem;
use App\Models\User;
use App\Models\Warehouse;
use App\StockAdjustmentStatus;
use Brick\Math\BigDecimal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StockAdjustmentService
{
    /**
     * The expected quantity is the count the operator reviewed, never an inventory write.
     * The unique adjustment number also identifies retries of one submission.
     *
     * @param  array{stock_item_id: int|string, warehouse_id: int|string, expected_quantity: string|int|float, new_quantity: string|int|float, reason: string, idempotency_key: string}  $data
     */
    public function adjust(array $data, User $actor): StockAdjustment
    {
        Gate::forUser($actor)->authorize('create', StockAdjustment::class);
        $data = Validator::make($data, (new StockAdjustmentRequest)->rules())->validate();
        $beforeExpected = (string) BigDecimal::of((string) $data['expected_quantity'])->toScale(4);
        $after = (string) BigDecimal::of((string) $data['new_quantity'])->toScale(4);
        $reason = trim($data['reason']);
        $number = 'ADJ-'.strtolower($data['idempotency_key']);

        try {
            return app(ActivityLogger::class)->transaction($actor, function () use ($data, $actor, $beforeExpected, $after, $reason, $number): StockAdjustment {
                if (! User::whereKey($actor->id)->where('is_active', true)->sharedLock()->exists()) {
                    throw ValidationException::withMessages(['actor' => 'An active responsible user is required.']);
                }
                $warehouse = Warehouse::whereKey($data['warehouse_id'])->sharedLock()->firstOrFail();
                $stockItem = StockItem::whereKey($data['stock_item_id'])->lockForUpdate()->firstOrFail();
                $existing = StockAdjustment::where('number', $number)->lockForUpdate()->first();
                if ($existing) {
                    $lines = $existing->items()->lockForUpdate()->get();
                    $line = $lines->first();
                    if ($existing->created_by !== $actor->id || $existing->warehouse_id !== $warehouse->id
                        || $existing->status !== StockAdjustmentStatus::Completed || $lines->count() !== 1
                        || ! $line || $line->stock_item_id !== $stockItem->id || $line->previous_quantity !== $beforeExpected
                        || $line->new_quantity !== $after || $line->reason !== $reason) {
                        throw ValidationException::withMessages(['idempotency_key' => 'This submission key has already been used for another adjustment.']);
                    }

                    return $existing;
                }
                $inventory = Inventory::where('stock_item_id', $stockItem->id)->where('warehouse_id', $warehouse->id)->lockForUpdate()->first();
                $before = $inventory?->quantity ?? '0.0000';
                if (! BigDecimal::of($before)->isEqualTo($beforeExpected)) {
                    throw ValidationException::withMessages(['expected_quantity' => 'Stock changed after you reviewed it. Refresh the current stock and confirm your physical count again.']);
                }
                $difference = (string) BigDecimal::of($after)->minus($before);
                if (BigDecimal::of($difference)->isZero()) {
                    throw ValidationException::withMessages(['new_quantity' => 'The new stock must differ from the current stock.']);
                }
                $adjustment = StockAdjustment::forceCreate([
                    'number' => $number, 'warehouse_id' => $warehouse->id,
                    'created_by' => $actor->id, 'status' => StockAdjustmentStatus::Completed,
                ]);
                app(InventoryService::class)->adjustStock($stockItem, $warehouse, $after, $actor, $reason, $adjustment->getMorphClass(), $adjustment->id);
                StockAdjustmentItem::forceCreate([
                    'stock_adjustment_id' => $adjustment->id, 'stock_item_id' => $stockItem->id,
                    'previous_quantity' => $before, 'new_quantity' => $after, 'difference' => $difference, 'reason' => $reason,
                ]);

                return $adjustment->load('items');
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['idempotency_key' => 'This adjustment submission already exists. Reload its history before trying again.']);
        }
    }
}
