<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Beverages',
                'description' => 'Soft drinks, water and other beverages',
            ],
            [
                'name' => 'Snacks',
                'description' => 'Chips and snack foods',
            ],
            [
                'name' => 'Canned Goods',
                'description' => 'Canned food products',
            ],
            [
                'name' => 'Personal Care',
                'description' => 'Personal hygiene products',
            ],
            [
                'name' => 'Household',
                'description' => 'Household and cleaning products',
            ],
            [
                'name' => 'School Supplies',
                'description' => 'School and office supplies',
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                [
                    'name' => $category['name'],
                ],
                [
                    'slug' => Str::slug($category['name']),
                    'description' => $category['description'],
                    'parent_id' => null,
                    'status' => 'active',
                    'is_active' => true,
                ]
            );
        }
    }
}