<?php

namespace Database\Factories;

use App\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\StockItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryMovement> */
class InventoryMovementFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'stock_item_id' => StockItem::factory(),
            'warehouse_id' => Warehouse::factory(),
            'performed_by' => User::factory(),
            'type' => InventoryMovementType::Purchase,
            'quantity_delta' => '1.0000',
            'quantity_before' => '0.0000',
            'quantity_after' => '1.0000',
            'occurred_at' => now(),
        ];
    }
}
