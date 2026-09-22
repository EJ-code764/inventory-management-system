<?php

namespace App\Services;

use App\Models\ProductBatch;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BatchAllocationService
{
    /**
     * Called only inside InventoryService's transaction after locking the stock item.
     * A lot number has immutable cost and expiration metadata within a warehouse.
     *
     * @param  array<string, mixed>  $data
     */
    public function resolve(int $stockItemId, int $warehouseId, string $cost, array $data): ProductBatch
    {
        $data = Validator::make($data, [
            'batch_number' => ['required', 'string', 'max:255'],
            'expiration_date' => ['nullable', 'date_format:Y-m-d'],
            'purchase_receipt_item_id' => ['nullable', 'integer', 'exists:purchase_receipt_items,id'],
        ])->validate();
        $number = trim($data['batch_number']);
        if ($number === '') {
            throw ValidationException::withMessages(['batch_number' => 'A batch number is required.']);
        }
        $batch = ProductBatch::where('stock_item_id', $stockItemId)->where('warehouse_id', $warehouseId)->where('batch_number', $number)->lockForUpdate()->first();
        if ($batch) {
            if ($batch->expiration_date?->format('Y-m-d') !== ($data['expiration_date'] ?? null) || ! BigDecimal::of($batch->unit_cost)->isEqualTo($cost)) {
                throw ValidationException::withMessages(['batch_number' => 'This batch already exists with a different expiration date or unit cost. Use a distinct lot number.']);
            }

            return $batch;
        }

        return ProductBatch::forceCreate([
            'stock_item_id' => $stockItemId, 'warehouse_id' => $warehouseId, 'batch_number' => $number,
            'unit_cost' => $cost, 'expiration_date' => $data['expiration_date'] ?? null,
            'purchase_receipt_item_id' => $data['purchase_receipt_item_id'] ?? null, 'quantity' => '0',
        ]);
    }

    /**
     * Untracked stock first, then receipt-order FIFO. Replace this allocation policy
     * with FEFO later without changing quantity mutation or movement recording.
     * Call under the InventoryService stock-item lock.
     *
     * @return array<int, array{batch: ?ProductBatch, quantity: string}>
     */
    public function allocate(int $stockItemId, int $warehouseId, BigDecimal $before, BigDecimal $amount, ?int $batchId = null): array
    {
        $batches = ProductBatch::where('stock_item_id', $stockItemId)->where('warehouse_id', $warehouseId)->orderBy('id')->lockForUpdate()->get();
        $tracked = BigDecimal::of('0');
        foreach ($batches as $batch) {
            if (BigDecimal::of($batch->quantity)->isNegative()) {
                throw ValidationException::withMessages(['batch' => 'A batch balance is invalid.']);
            }
            $tracked = $tracked->plus($batch->quantity);
        }
        if ($tracked->isGreaterThan($before)) {
            throw ValidationException::withMessages(['batch' => 'Batch quantities exceed the inventory balance. Reconcile the data before proceeding.']);
        }
        if ($batchId !== null) {
            $batch = $batches->firstWhere('id', $batchId);
            if (! $batch || BigDecimal::of($batch->quantity)->isLessThan($amount)) {
                throw ValidationException::withMessages(['batch' => 'The selected batch does not have sufficient stock in this warehouse.']);
            }

            return [['batch' => $batch, 'quantity' => (string) $amount]];
        }
        $allocations = [];
        $untracked = $before->minus($tracked);
        $remaining = $amount;
        if ($untracked->isPositive()) {
            $take = BigDecimal::min($remaining, $untracked);
            $allocations[] = ['batch' => null, 'quantity' => (string) $take];
            $remaining = $remaining->minus($take);
        }
        foreach ($batches as $batch) {
            if ($remaining->isZero()) {
                break;
            }
            if (BigDecimal::of($batch->quantity)->isZero()) {
                continue;
            }
            $take = BigDecimal::min($remaining, BigDecimal::of($batch->quantity));
            $allocations[] = ['batch' => $batch, 'quantity' => (string) $take];
            $remaining = $remaining->minus($take);
        }
        if (! $remaining->isZero()) {
            throw ValidationException::withMessages(['batch' => 'Insufficient batch stock.']);
        }

        return $allocations;
    }
}
