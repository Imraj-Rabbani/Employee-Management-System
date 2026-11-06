<?php

namespace App\Modules\Hrm\Database\Factories;

use App\Modules\Hrm\Models\Designation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake Designation rows for tests and demo data: Designation::factory()->create().
 *
 * @extends Factory<Designation>
 */
class DesignationFactory extends Factory
{
    protected $model = Designation::class;

    public function definition(): array
    {
        return [
            'code' => 'D-'.fake()->unique()->numerify('####'),
            'name' => fake()->unique()->jobTitle().' '.fake()->unique()->numberBetween(1, 9999),
        ];
    }
}
