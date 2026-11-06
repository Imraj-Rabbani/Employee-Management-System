<?php

namespace App\Modules\Hrm\Database\Factories;

use App\Modules\Hrm\Models\EmploymentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake EmploymentType rows for tests and demo data: EmploymentType::factory()->create().
 *
 * @extends Factory<EmploymentType>
 */
class EmploymentTypeFactory extends Factory
{
    protected $model = EmploymentType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Permanent', 'Contract', 'Part time', 'Probation', 'Intern', 'Casual']).' '.fake()->unique()->numberBetween(1, 9999),
        ];
    }
}
