<?php

namespace Database\Factories;

use App\Models\ProductBatch;
use App\Models\StockItem;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductBatch> */
class ProductBatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'stock_item_id' => StockItem::factory(), 'warehouse_id' => Warehouse::factory(),
            'batch_number' => fake()->unique()->bothify('LOT-########'),
            'quantity' => '0.0000', 'unit_cost' => '10.0000', 'expiration_date' => today()->addDays(30),
        ];
    }
}
