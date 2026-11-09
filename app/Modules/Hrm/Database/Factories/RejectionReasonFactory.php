<?php

namespace App\Modules\Hrm\Database\Factories;

use App\Modules\Hrm\Models\RejectionReason;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake RejectionReason rows for tests and demo data: RejectionReason::factory()->create().
 *
 * @extends Factory<RejectionReason>
 */
class RejectionReasonFactory extends Factory
{
    protected $model = RejectionReason::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Not enough experience', 'Salary expectation too high', 'Did not attend'])
                .' '.fake()->unique()->numberBetween(1, 9999),
        ];
    }
}
