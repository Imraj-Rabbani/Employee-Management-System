<?php

namespace App\Modules\Hrm\Database\Factories;

use App\Modules\Hrm\Models\InterviewQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake interview questions for tests and demo data: InterviewQuestion::factory()->create().
 *
 * @extends Factory<InterviewQuestion>
 */
class InterviewQuestionFactory extends Factory
{
    protected $model = InterviewQuestion::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Communication', 'Technical skill', 'Attitude', 'Experience', 'Leadership'])
                .' '.fake()->unique()->numberBetween(1, 9999),
            'sequence' => fake()->numberBetween(1, 50),
        ];
    }
}
