<?php

namespace App\Models;

use Database\Factories\PurchaseReceiptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReceipt extends Model
{
    /** @use HasFactory<PurchaseReceiptFactory> */
    use HasFactory;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(function (PurchaseReceipt $receipt): void {
            throw new \LogicException('Purchase receipts are immutable.');
        });
        static::deleting(function (PurchaseReceipt $receipt): void {
            throw new \LogicException('Purchase receipts cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }
}
