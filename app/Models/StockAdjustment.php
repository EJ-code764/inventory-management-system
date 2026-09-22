<?php

namespace App\Models;

use App\StockAdjustmentStatus;
use Database\Factories\StockAdjustmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class StockAdjustment extends Model
{
    /** @use HasFactory<StockAdjustmentFactory> */
    use HasFactory;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['status' => StockAdjustmentStatus::class];
    }

    protected static function booted(): void
    {
        static::updating(function (StockAdjustment $adjustment): void {
            throw new \LogicException('Stock adjustments are immutable. Record another correction instead.');
        });
        static::deleting(function (StockAdjustment $adjustment): void {
            throw new \LogicException('Stock adjustments cannot be deleted.');
        });
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function movements(): MorphMany
    {
        return $this->morphMany(InventoryMovement::class, 'reference');
    }
}
