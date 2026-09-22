<?php

namespace App\Services;

use App\InventoryMovementType;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\StockItem;
use App\Models\User;
use App\Models\Warehouse;
use App\ProductStatus;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    private const MAX_QUANTITY = '9999999999999999.9999';

    /**
     * Trusted domain API: the calling workflow must authorize its business action.
     * Pass decimal strings to preserve exact quantities and prices.
     *
     * @param  array{batch_number: string, expiration_date?: ?string, purchase_receipt_item_id?: ?int}|null  $batch
     */
    public function increaseStock(StockItem $item, Warehouse $warehouse, string|int|float $quantity, InventoryMovementType $type, User $actor, ?string $referenceType = null, ?int $referenceId = null, ?string $remarks = null, string|int|float|null $unitCost = null, ?array $batch = null): Inventory
    {
        if (! in_array($type, [InventoryMovementType::Purchase, InventoryMovementType::SaleReturn, InventoryMovementType::AdjustmentIn, InventoryMovementType::TransferIn], true)) {
            throw ValidationException::withMessages(['type' => 'This movement type cannot increase stock.']);
        }

        return $this->change($item, $warehouse, $quantity, $type, $actor, $referenceType, $referenceId, $remarks, $unitCost, batch: $batch);
    }

    public function decreaseStock(StockItem $item, Warehouse $warehouse, string|int|float $quantity, InventoryMovementType $type, User $actor, ?string $referenceType = null, ?int $referenceId = null, ?string $remarks = null, string|int|float|null $unitCost = null, ?int $batchId = null): Inventory
    {
        if (! in_array($type, [InventoryMovementType::Sale, InventoryMovementType::PurchaseReturn, InventoryMovementType::AdjustmentOut, InventoryMovementType::TransferOut, InventoryMovementType::Damage, InventoryMovementType::Expired], true)) {
            throw ValidationException::withMessages(['type' => 'This movement type cannot decrease stock.']);
        }

        return $this->change($item, $warehouse, $quantity, $type, $actor, $referenceType, $referenceId, $remarks, $unitCost, decrease: true, batchId: $batchId);
    }

    /** The quantity is the new physical count, not a signed delta. */
    public function adjustStock(StockItem $item, Warehouse $warehouse, string|int|float $quantity, User $actor, string $remarks, ?string $referenceType = null, ?int $referenceId = null): Inventory
    {
        if (trim($remarks) === '') {
            throw ValidationException::withMessages(['remarks' => 'An adjustment reason is required.']);
        }

        return $this->change($item, $warehouse, $quantity, InventoryMovementType::AdjustmentIn, $actor, $referenceType, $referenceId, $remarks, null, adjustment: true);
    }

    /**
     * Legacy method names delegate to the same mutation boundary.
     * Background jobs must supply an actor; actorless changes are not permitted.
     */
    public function increase(StockItem $item, Warehouse $warehouse, string|int|float $quantity, InventoryMovementType $type, ?User $actor = null, ?Model $reference = null, ?string $reason = null, string|int|float|null $unitCost = null): Inventory
    {
        return $this->increaseStock($item, $warehouse, $quantity, $type, $this->actor($actor), $reference?->getMorphClass(), $reference?->getKey(), $reason, $unitCost);
    }

    public function decrease(StockItem $item, Warehouse $warehouse, string|int|float $quantity, InventoryMovementType $type, ?User $actor = null, ?Model $reference = null, ?string $reason = null): Inventory
    {
        return $this->decreaseStock($item, $warehouse, $quantity, $type, $this->actor($actor), $reference?->getMorphClass(), $reference?->getKey(), $reason);
    }

    public function adjust(StockItem $item, Warehouse $warehouse, string|int|float $quantity, User $actor, Model $reference, string $reason): Inventory
    {
        return $this->adjustStock($item, $warehouse, $quantity, $actor, $reason, $reference->getMorphClass(), $reference->getKey());
    }

    /** Preserve each source lot and its metadata across the paired transfer operation. */
    public function transfer(StockItem $item, Warehouse $source, Warehouse $destination, string|int|float $quantity, User $actor, Model $reference): void
    {
        if ($source->is($destination)) {
            throw ValidationException::withMessages(['destination_warehouse_id' => 'The destination warehouse must differ from the source warehouse.']);
        }
        app(ActivityLogger::class)->transaction($actor, function () use ($item, $source, $destination, $quantity, $actor, $reference): void {
            Warehouse::whereIn('id', [$source->id, $destination->id])->orderBy('id')->sharedLock()->get();
            StockItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $lastId = InventoryMovement::where('stock_item_id', $item->id)->where('warehouse_id', $source->id)->latest('id')->lockForUpdate()->value('id') ?? 0;
            $this->decrease($item, $source, $quantity, InventoryMovementType::TransferOut, $actor, $reference);
            $movements = InventoryMovement::with('productBatch')->where('stock_item_id', $item->id)->where('warehouse_id', $source->id)->where('id', '>', $lastId)->orderBy('id')->lockForUpdate()->get();
            foreach ($movements as $movement) {
                $lot = $movement->productBatch;
                $metadata = $lot ? ['batch_number' => $lot->batch_number, 'expiration_date' => $lot->expiration_date?->format('Y-m-d')] : null;
                $this->increaseStock(
                    $item, $destination, (string) BigDecimal::of($movement->quantity_delta)->abs(),
                    InventoryMovementType::TransferIn, $actor, $reference->getMorphClass(), $reference->getKey(),
                    unitCost: $movement->unit_cost, batch: $metadata,
                );
            }
        }, 3);
    }

    private function actor(?User $actor): User
    {
        $actor ??= auth()->user();
        if (! $actor) {
            throw ValidationException::withMessages(['actor' => 'A responsible user is required.']);
        }

        return $actor;
    }

    private function decimal(string|int|float $value, string $field, bool $allowZero = true): BigDecimal
    {
        if (is_float($value) && ! is_finite($value)) {
            throw ValidationException::withMessages([$field => 'The value must be a finite decimal.']);
        }
        $value = (string) $value;
        if (! preg_match('/^\d{1,16}(\.\d{1,4})?$/D', $value)) {
            throw ValidationException::withMessages([$field => 'Use a non-negative decimal with at most 16 whole digits and 4 decimal places.']);
        }
        $decimal = BigDecimal::of($value)->toScale(4);
        if (! $allowZero && $decimal->isZero()) {
            throw ValidationException::withMessages([$field => 'The quantity must be greater than zero.']);
        }

        return $decimal;
    }

    private function change(StockItem $item, Warehouse $warehouse, string|int|float $quantity, InventoryMovementType $type, User $actor, ?string $referenceType, ?int $referenceId, ?string $remarks, string|int|float|null $unitCost, bool $decrease = false, bool $adjustment = false, ?array $batch = null, ?int $batchId = null): Inventory
    {
        $amount = $this->decimal($quantity, 'quantity', $adjustment);
        $cost = $unitCost === null ? null : (string) $this->decimal($unitCost, 'unit_cost');
        if (in_array($type, [InventoryMovementType::AdjustmentIn, InventoryMovementType::AdjustmentOut], true) && trim($remarks ?? '') === '') {
            throw ValidationException::withMessages(['remarks' => 'An adjustment reason is required.']);
        }
        if (($referenceType === null) !== ($referenceId === null) || ($referenceType !== null && (trim($referenceType) === '' || mb_strlen($referenceType) > 255 || $referenceId < 1))) {
            throw ValidationException::withMessages(['reference' => 'Provide both a valid reference type and positive reference ID, or neither.']);
        }
        if ($remarks !== null && mb_strlen($remarks) > 255) {
            throw ValidationException::withMessages(['remarks' => 'Remarks must not exceed 255 characters.']);
        }

        return app(ActivityLogger::class)->transaction($actor, function () use ($item, $warehouse, $amount, $type, $actor, $referenceType, $referenceId, $remarks, $cost, $decrease, $adjustment, $batch, $batchId): Inventory {
            if (! $actor->exists || ! User::whereKey($actor->id)->where('is_active', true)->sharedLock()->exists()) {
                throw ValidationException::withMessages(['actor' => 'An active responsible user is required.']);
            }
            $location = Warehouse::whereKey($warehouse->id)->sharedLock()->firstOrFail();
            if (! $location->is_active) {
                throw ValidationException::withMessages(['warehouse' => 'This warehouse is inactive.']);
            }

            /**
             * Lock a stable parent before creating a missing balance. This serializes
             * mutations for one SKU, including its first receipt, without locking
             * unrelated SKUs. The composite unique key is the final duplicate guard.
             */
            $stockItem = StockItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $stockItem->load(['product', 'variant.product']);
            $product = $stockItem->product ?? $stockItem->variant?->product;
            $validOwner = ($stockItem->product_id === null) !== ($stockItem->product_variant_id === null);
            if (! $validOwner || ! $product || ($stockItem->product_id !== null && $product->has_variants)) {
                throw ValidationException::withMessages(['stock_item' => 'The stock item must identify one simple product or variant.']);
            }
            if (! $stockItem->is_active || $product->status !== ProductStatus::Active || ($stockItem->variant && ! $stockItem->variant->is_active)) {
                throw ValidationException::withMessages(['stock_item' => 'This product or variant is inactive.']);
            }

            $inventory = Inventory::where('stock_item_id', $stockItem->id)->where('warehouse_id', $location->id)->lockForUpdate()->first();
            $inventory ??= Inventory::create(['stock_item_id' => $stockItem->id, 'warehouse_id' => $location->id]);
            $before = $this->decimal($inventory->quantity, 'quantity');
            $reserved = $this->decimal($inventory->reserved_quantity, 'reserved_quantity');
            if ($reserved->isGreaterThan($before)) {
                throw ValidationException::withMessages(['reserved_quantity' => 'Reserved stock exceeds the current balance.']);
            }
            $after = $adjustment ? $amount : ($decrease ? $before->minus($amount) : $before->plus($amount));
            if ($after->isNegative() || $after->isLessThan($reserved)) {
                throw ValidationException::withMessages(['quantity' => 'Insufficient available inventory. Reserved stock cannot be consumed.']);
            }
            if ($after->isGreaterThan(self::MAX_QUANTITY)) {
                throw ValidationException::withMessages(['quantity' => 'The resulting quantity exceeds the supported maximum.']);
            }
            $delta = $after->minus($before);
            if ($delta->isZero()) {
                throw ValidationException::withMessages(['quantity' => 'The adjusted quantity must differ from the current quantity.']);
            }
            $movementType = $adjustment
                ? ($delta->isPositive() ? InventoryMovementType::AdjustmentIn : InventoryMovementType::AdjustmentOut)
                : $type;
            $allocator = app(BatchAllocationService::class);
            if ($delta->isPositive()) {
                $allocator->allocate($stockItem->id, $location->id, $before, BigDecimal::of('0'));
            }
            $allocations = $delta->isNegative()
                ? $allocator->allocate($stockItem->id, $location->id, $before, $delta->abs(), $batchId)
                : [['batch' => $batch === null ? null : $allocator->resolve($stockItem->id, $location->id, $cost ?? '0.0000', $batch), 'quantity' => (string) $delta]];
            if ($batch !== null && $cost === null) {
                throw ValidationException::withMessages(['unit_cost' => 'A unit cost is required for batch receipts.']);
            }
            $balance = $before;
            foreach ($allocations as $allocation) {
                $lot = $allocation['batch'];
                $part = BigDecimal::of($allocation['quantity']);
                $signed = $delta->isNegative() ? $part->negated() : $part;
                if ($lot) {
                    $lot->forceFill(['quantity' => (string) BigDecimal::of($lot->quantity)->plus($signed)])->save();
                }
                $next = $balance->plus($signed);
                InventoryMovement::forceCreate([
                    'stock_item_id' => $stockItem->id, 'warehouse_id' => $location->id,
                    'product_batch_id' => $lot?->id, 'performed_by' => $actor->id, 'type' => $movementType,
                    'quantity_delta' => (string) $signed, 'quantity_before' => (string) $balance, 'quantity_after' => (string) $next,
                    'unit_cost' => $lot?->unit_cost ?? $cost, 'reference_type' => $referenceType,
                    'reference_id' => $referenceId, 'reason' => $remarks, 'occurred_at' => now(),
                ]);
                $balance = $next;
            }
            $inventory->forceFill(['quantity' => (string) $after])->save();

            return $inventory->refresh();
        }, 3);
    }
}
