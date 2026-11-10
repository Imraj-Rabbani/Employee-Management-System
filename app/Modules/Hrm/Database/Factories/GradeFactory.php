<?php

namespace App\Modules\Hrm\Database\Factories;

use App\Modules\Hrm\Models\Grade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake Grade rows for tests and demo data: Grade::factory()->create().
 *
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    protected $model = Grade::class;

    public function definition(): array
    {
        return [
            'code' => 'G-'.fake()->unique()->numerify('###'),
            'name' => 'Grade '.fake()->unique()->numberBetween(1, 9999),
            'sequence' => fake()->numberBetween(1, 50),
        ];
    }
}
