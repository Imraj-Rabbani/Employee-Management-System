<?php

namespace App\Modules\Hrm\Database\Factories;

use App\Modules\Hrm\Models\InterviewFactor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake InterviewFactor rows for tests and demo data: InterviewFactor::factory()->create().
 *
 * @extends Factory<InterviewFactor>
 */
class InterviewFactorFactory extends Factory
{
    protected $model = InterviewFactor::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Communication', 'Technical skill', 'Attitude', 'Experience', 'Leadership'])
                .' '.fake()->unique()->numberBetween(1, 9999),
            'weight' => fake()->randomElement([10, 20, 25]),
            'sequence' => fake()->numberBetween(1, 50),
        ];
    }

    public function required(): static
    {
        return $this->state(['is_required' => true]);
    }
}
