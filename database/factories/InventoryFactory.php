<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\StockItem;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Inventory> */
class InventoryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'stock_item_id' => StockItem::factory(),
            'warehouse_id' => Warehouse::factory(),
            'quantity' => '0.0000',
            'reserved_quantity' => '0.0000',
            'reorder_level' => null,
        ];
    }
}
