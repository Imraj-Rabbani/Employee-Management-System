<?php

namespace App\Modules\Hrm\Database\Factories;

use App\Modules\Hrm\Enums\Gender;
use App\Modules\Hrm\Models\Applicant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake applicants: Applicant::factory()->create(), with a unique email and Bangladesh mobile number.
 *
 * @extends Factory<Applicant>
 */
class ApplicantFactory extends Factory
{
    protected $model = Applicant::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '017'.fake()->unique()->numerify('########'),
            'gender' => fake()->randomElement(Gender::cases()),
            'birth_date' => fake()->dateTimeBetween('-40 years', '-20 years')->format('Y-m-d'),
            'current_employer' => fake()->company(),
            'expected_salary' => fake()->randomElement([30000, 45000, 60000]),
        ];
    }
}
