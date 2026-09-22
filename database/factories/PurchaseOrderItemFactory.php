<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseOrderItem> */
class PurchaseOrderItemFactory extends Factory
{
    public function definition(): array
    {
        return ['purchase_order_id' => PurchaseOrder::factory(), 'stock_item_id' => StockItem::factory(),
            'ordered_quantity' => '2.0000', 'received_quantity' => '0.0000', 'unit_cost' => '10.0000',
            'discount' => '0.0000', 'tax' => '0.0000', 'subtotal' => '20.0000'];
    }
}
