<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            [
                'name' => 'Piece',
                'code' => 'PCS',
                'short_name' => 'pcs',
            ],
            [
                'name' => 'Bottle',
                'code' => 'BTL',
                'short_name' => 'btl',
            ],
            [
                'name' => 'Box',
                'code' => 'BOX',
                'short_name' => 'box',
            ],
            [
                'name' => 'Pack',
                'code' => 'PACK',
                'short_name' => 'pack',
            ],
        ];

        foreach ($units as $unit) {
            Unit::updateOrCreate(
                ['code' => $unit['code']],
                [
                    'name' => $unit['name'],
                    'short_name' => $unit['short_name'],
                    'status' => 'active',
                    'is_active' => true,
                ]
            );
        }
    }
}