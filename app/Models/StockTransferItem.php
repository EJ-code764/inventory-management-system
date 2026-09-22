<?php

namespace App\Models;

use App\StockTransferStatus;
use Database\Factories\StockTransferItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends Model
{
    /** @use HasFactory<StockTransferItemFactory> */
    use HasFactory;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }

    protected static function booted(): void
    {
        static::saving(function (StockTransferItem $item): void {
            $ids = array_filter([$item->stock_transfer_id, $item->getRawOriginal('stock_transfer_id')]);
            if (StockTransfer::whereIn('id', $ids)->where('status', '!=', StockTransferStatus::Draft->value)->exists()) {
                throw new \LogicException('Only draft transfer items can be changed.');
            }
        });
        static::deleting(function (StockTransferItem $item): void {
            if ($item->stockTransfer()->firstOrFail()->status !== StockTransferStatus::Draft) {
                throw new \LogicException('Only draft transfer items can be deleted.');
            }
        });
    }

    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }
}
