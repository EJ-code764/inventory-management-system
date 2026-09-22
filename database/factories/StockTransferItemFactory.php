<?php

namespace Database\Factories;

use App\Models\StockItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockTransferItem> */
class StockTransferItemFactory extends Factory
{
    public function definition(): array
    {
        return ['stock_transfer_id' => StockTransfer::factory(), 'stock_item_id' => StockItem::factory(), 'quantity' => '2.0000'];
    }
}
