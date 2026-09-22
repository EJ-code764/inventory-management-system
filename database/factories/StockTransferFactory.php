<?php

namespace Database\Factories;

use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\StockTransferStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<StockTransfer> */
class StockTransferFactory extends Factory
{
    public function definition(): array
    {
        return ['number' => 'TR-'.Str::ulid(), 'source_warehouse_id' => Warehouse::factory(), 'destination_warehouse_id' => Warehouse::factory(),
            'created_by' => User::factory(), 'status' => StockTransferStatus::Draft, 'transfer_date' => today(), 'revision' => 1];
    }
}
