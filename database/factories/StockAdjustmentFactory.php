<?php

namespace Database\Factories;

use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\Warehouse;
use App\StockAdjustmentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<StockAdjustment> */
class StockAdjustmentFactory extends Factory
{
    public function definition(): array
    {
        return ['number' => 'ADJ-'.Str::uuid(), 'warehouse_id' => Warehouse::factory(), 'created_by' => User::factory(), 'status' => StockAdjustmentStatus::Completed];
    }
}
