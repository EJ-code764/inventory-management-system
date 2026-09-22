<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'category_id' => Category::factory(),
            'unit_id' => Unit::factory(),
            'brand_id' => null,
            'status' => 'active',
        ];
    }

    public function withStockItem(): static
    {
        return $this->afterCreating(function (Product $product): void {
            $product->stockItem()->create([
                'sku' => fake()->unique()->bothify('SKU-????-########'),
                'barcode' => null,
                'cost_price' => '10.0000',
                'selling_price' => '15.0000',
                'reorder_level' => '5.0000',
                'is_active' => $product->status->value === 'active',
            ]);
        });
    }
}
