<?php

namespace App\Models;

use App\InventoryMovementType;
use Database\Factories\InventoryMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryMovement extends Model
{
    /** @use HasFactory<InventoryMovementFactory> */
    use HasFactory;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(function (InventoryMovement $movement): void {
            throw new \LogicException('Inventory movements are immutable. Record a correcting movement instead.');
        });
        static::deleting(function (InventoryMovement $movement): void {
            throw new \LogicException('Inventory movements cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return ['type' => InventoryMovementType::class, 'quantity_delta' => 'decimal:4', 'quantity_before' => 'decimal:4', 'quantity_after' => 'decimal:4', 'unit_cost' => 'decimal:4', 'occurred_at' => 'datetime'];
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function productBatch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
