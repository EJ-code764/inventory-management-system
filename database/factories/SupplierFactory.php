<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Supplier> */
class SupplierFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'supplier_code' => fake()->unique()->bothify('SUP-########'),
            'name' => fake()->unique()->company(),
            'contact_person' => fake()->name(),
            'phone' => fake()->numerify('+639#########'),
            'email' => fake()->safeEmail(),
            'address' => fake()->address(),
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => 'inactive']);
    }
}
