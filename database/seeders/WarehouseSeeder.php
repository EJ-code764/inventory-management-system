<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        Warehouse::updateOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => 'Main Warehouse',
                'address' => 'Main Store',
                'status' => 'active',
                'is_active' => true,
            ]
        );
    }
}