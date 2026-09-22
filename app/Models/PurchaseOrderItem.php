<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use Database\Factories\PurchaseOrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    /** @use HasFactory<PurchaseOrderItemFactory> */
    use HasFactory;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['ordered_quantity' => 'decimal:4', 'received_quantity' => 'decimal:4', 'unit_cost' => 'decimal:4',
            'discount' => 'decimal:4', 'tax' => 'decimal:4', 'subtotal' => 'decimal:4'];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function remainingQuantity(): string
    {
        return (string) BigDecimal::of($this->ordered_quantity)->minus($this->received_quantity);
    }
}
