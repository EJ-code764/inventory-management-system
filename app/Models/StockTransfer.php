<?php

namespace App\Models;

use App\StockTransferStatus;
use Database\Factories\StockTransferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class StockTransfer extends Model
{
    /** @use HasFactory<StockTransferFactory> */
    use HasFactory;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['status' => StockTransferStatus::class, 'transfer_date' => 'date', 'completed_at' => 'datetime', 'revision' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (StockTransfer $transfer): void {
            if ($transfer->getRawOriginal('status') !== StockTransferStatus::Draft->value) {
                throw new \LogicException('Completed or cancelled transfers cannot be changed.');
            }
        });
        static::deleting(function (StockTransfer $transfer): void {
            throw new \LogicException('Cancel a draft transfer instead of deleting its history.');
        });
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function movements(): MorphMany
    {
        return $this->morphMany(InventoryMovement::class, 'reference');
    }
}
