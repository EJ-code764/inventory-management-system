<?php

namespace App\Models;

use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (ProductVariant $variant): void {
            if ($variant->isDirty('product_id')) {
                throw new \LogicException('A variant cannot be reassigned to another product.');
            }
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'attributes' => 'array'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockItem(): HasOne
    {
        return $this->hasOne(StockItem::class);
    }
}
