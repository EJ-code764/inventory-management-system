<?php

namespace App\Models;

use Database\Factories\StockAdjustmentItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentItem extends Model
{
    /** @use HasFactory<StockAdjustmentItemFactory> */
    use HasFactory;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['previous_quantity' => 'decimal:4', 'new_quantity' => 'decimal:4', 'difference' => 'decimal:4'];
    }

    protected static function booted(): void
    {
        static::updating(function (StockAdjustmentItem $item): void {
            throw new \LogicException('Adjustment items are immutable.');
        });
        static::deleting(function (StockAdjustmentItem $item): void {
            throw new \LogicException('Adjustment items cannot be deleted.');
        });
    }

    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }
}
