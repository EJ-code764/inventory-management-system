<?php

namespace App\Models;

use Database\Factories\StockItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockItem extends Model
{
    /** @use HasFactory<StockItemFactory> */
    use HasFactory;

    protected $fillable = ['product_id', 'product_variant_id', 'sku', 'barcode', 'cost_price', 'selling_price', 'reorder_level', 'is_active'];

    protected static function booted(): void
    {
        static::updating(function (StockItem $item): void {
            if ($item->isDirty(['product_id', 'product_variant_id'])) {
                throw new \LogicException('A stock identity cannot be reassigned to another product or variant.');
            }
        });
    }

    protected function casts(): array
    {
        return ['cost_price' => 'decimal:4', 'selling_price' => 'decimal:4', 'reorder_level' => 'decimal:4', 'is_active' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
