<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Unit> */
class UnitFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->bothify('Classification ?????-#####'), 'short_name' => 'pc', 'status' => 'active'];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => 'inactive']);
    }
}
