<?php

namespace App\Models;

use Database\Factories\ProductBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductBatch extends Model
{
    /** @use HasFactory<ProductBatchFactory> */
    use HasFactory;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_cost' => 'decimal:4', 'expiration_date' => 'date'];
    }

    protected static function booted(): void
    {
        static::updating(function (ProductBatch $batch): void {
            if ($batch->isDirty(['stock_item_id', 'warehouse_id', 'batch_number', 'unit_cost', 'expiration_date', 'purchase_receipt_item_id'])) {
                throw new \LogicException('Batch identity and receipt metadata are immutable.');
            }
        });
        static::deleting(function (ProductBatch $batch): void {
            throw new \LogicException('Batch history must be retained, including expired and depleted batches.');
        });
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function purchaseReceiptItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceiptItem::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
