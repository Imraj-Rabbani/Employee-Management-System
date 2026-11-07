<?php

namespace App\Modules\Hrm\Database\Factories;

use App\Modules\Hrm\Enums\EmployeeStatus;
use App\Modules\Hrm\Enums\Gender;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmploymentType;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Hrm\Models\OrgUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake Employee rows for tests and demo data.
 *
 *     Employee::factory()->create()                         in a new org unit, Active
 *     Employee::factory()->inUnit($finance)->create()
 *     Employee::factory()->withJob()->create()              + designation, grade, employment type
 *     Employee::factory()->separated()->create()
 *     Employee::factory()->count(20)->inUnit($sales)->create()
 *
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        $gender = fake()->randomElement([Gender::Male, Gender::Female]);

        return [
            'code' => 'E-'.fake()->unique()->numerify('#####'),
            'first_name' => fake()->firstName($gender === Gender::Male ? 'male' : 'female'),
            'last_name' => fake()->lastName(),
            'gender' => $gender,
            'birth_date' => fake()->dateTimeBetween('-55 years', '-20 years')->format('Y-m-d'),
            'mobile' => fake()->numerify('01#########'),
            'office_email' => fake()->unique()->safeEmail(),
            'join_date' => fake()->dateTimeBetween('-10 years', 'now')->format('Y-m-d'),
            'org_unit_id' => OrgUnit::factory(),
            'status' => EmployeeStatus::Active,
        ];
    }

    public function inUnit(OrgUnit $unit): static
    {
        return $this->state(['org_unit_id' => $unit->id]);
    }

    /** Fill the main job fields with new org records. */
    public function withJob(): static
    {
        return $this->state([
            'employment_type_id' => EmploymentType::factory(),
            'designation_id' => Designation::factory(),
            'grade_id' => Grade::factory(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(['status' => EmployeeStatus::Suspended]);
    }

    public function separated(): static
    {
        return $this->state(fn () => [
            'status' => EmployeeStatus::Separated,
            'separation_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
        ]);
    }
}
