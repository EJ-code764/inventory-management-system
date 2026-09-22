<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use Database\Factories\InventoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    /** @use HasFactory<InventoryFactory> */
    use HasFactory;

    protected $fillable = ['warehouse_id', 'stock_item_id', 'reorder_level'];

    protected $attributes = ['quantity' => '0.0000', 'reserved_quantity' => '0.0000'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'reserved_quantity' => 'decimal:4', 'reorder_level' => 'decimal:4'];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function availableQuantity(): string
    {
        return (string) BigDecimal::of($this->quantity)->minus($this->reserved_quantity)->toScale(4);
    }

    public function effectiveReorderLevel(): string
    {
        return $this->reorder_level ?? $this->stockItem->reorder_level;
    }

    public function isLowStock(): bool
    {
        return BigDecimal::of($this->quantity)->isLessThanOrEqualTo($this->effectiveReorderLevel());
    }

    public function stockStatus(): string
    {
        if (BigDecimal::of($this->quantity)->isZero()) {
            return 'Out of stock';
        }

        return $this->isLowStock() ? 'Low stock' : 'In stock';
    }
}
