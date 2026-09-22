<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ActivityLog> */
class ActivityLogFactory extends Factory
{
    public function definition(): array
    {
        return ['event' => 'product.updated', 'description' => 'product.updated',
            'subject_type' => Product::class, 'subject_id' => 1,
            'properties' => ['old' => ['name' => 'Previous name'], 'new' => ['name' => 'Updated name']]];
    }
}
