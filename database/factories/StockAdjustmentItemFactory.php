<?php

namespace Database\Factories;

use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockAdjustmentItem> */
class StockAdjustmentItemFactory extends Factory
{
    public function definition(): array
    {
        return ['stock_adjustment_id' => StockAdjustment::factory(), 'stock_item_id' => StockItem::factory(),
            'previous_quantity' => '100.0000', 'new_quantity' => '97.0000', 'difference' => '-3.0000', 'reason' => 'Physical count'];
    }
}
