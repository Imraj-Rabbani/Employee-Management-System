<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('????'),
            'name' => fake()->company(),
            // A closure gets the other attributes, so a code given in the test is used here too.
            'database' => fn (array $attributes) => Tenant::databaseNameFor($attributes['code']),
            'modules' => ['hrm', 'payroll', 'workflow'],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
