<?php

namespace App\Models;

use Database\Factories\PurchaseReceiptItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReceiptItem extends Model
{
    /** @use HasFactory<PurchaseReceiptItemFactory> */
    use HasFactory;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(function (PurchaseReceiptItem $item): void {
            throw new \LogicException('Receipt items are immutable.');
        });
        static::deleting(function (PurchaseReceiptItem $item): void {
            throw new \LogicException('Receipt items cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_cost' => 'decimal:4', 'expiration_date' => 'date'];
    }

    public function purchaseReceipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }
}
