<?php

namespace Database\Factories;

use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseReceiptItem> */
class PurchaseReceiptItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'purchase_order_item_id' => PurchaseOrderItem::factory(),
            'purchase_receipt_id' => fn (array $attributes): int => PurchaseReceipt::factory()->create(['purchase_order_id' => PurchaseOrderItem::findOrFail($attributes['purchase_order_item_id'])->purchase_order_id])->id,
            'stock_item_id' => fn (array $attributes): int => PurchaseOrderItem::findOrFail($attributes['purchase_order_item_id'])->stock_item_id,
            'quantity' => '1.0000',
            'unit_cost' => fn (array $attributes): string => PurchaseOrderItem::findOrFail($attributes['purchase_order_item_id'])->unit_cost,
        ];
    }
}
