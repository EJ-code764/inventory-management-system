<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockItem> */
class StockItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => fake()->unique()->bothify('STOCK-????-########'),
            'barcode' => null,
            'cost_price' => '10.0000',
            'selling_price' => '15.0000',
            'reorder_level' => '5.0000',
            'is_active' => true,
        ];
    }
}
