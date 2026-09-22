<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            'Coca-Cola',
            'Sprite',
            'Jack n Jill',
            'Century',
            'Argentina',
            'Safeguard',
            'Tide',
            'HBW',
            'Generic',
        ];

        foreach ($brands as $name) {
            Brand::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => null,
                    'status' => 'active',
                    'is_active' => true,
                ]
            );
        }
    }
}