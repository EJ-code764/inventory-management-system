<?php

namespace Database\Factories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Brand> */
class BrandFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->bothify('Classification ?????-#####'), 'description' => fake()->sentence(), 'status' => 'active'];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => 'inactive']);
    }
}
