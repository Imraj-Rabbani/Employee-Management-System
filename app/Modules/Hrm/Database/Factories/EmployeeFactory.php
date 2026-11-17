<?php

namespace App\Modules\Hrm\Database\Factories;

use App\Modules\Hrm\Enums\EmployeeStatus;
use App\Modules\Hrm\Enums\Gender;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeEntity;
use App\Modules\Hrm\Models\EmploymentType;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Hrm\Models\Grade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake Employee rows for tests and demo data.
 *
 *     Employee::factory()->create()                         Active, in no entity
 *     Employee::factory()->inEntity($finance)->create()     + current in Finance from the join date
 *     Employee::factory()->inEntity($finance, $gulshan)->create()   one entity per type
 *     Employee::factory()->withJob()->create()              + designation, grade, employment type
 *     Employee::factory()->separated()->create()
 *     Employee::factory()->count(20)->inEntity($sales)->create()
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
            'status' => EmployeeStatus::Active,
        ];
    }

    /**
     * After creating, put the employee in these entities from their join date (one per type).
     * Writes the rows directly (not through ChangeEntity): test data, no rules to check.
     */
    public function inEntity(Entity ...$entities): static
    {
        return $this->afterCreating(function (Employee $employee) use ($entities) {
            foreach ($entities as $entity) {
                EmployeeEntity::create([
                    'employee_id' => $employee->id,
                    'entity_type_id' => $entity->entity_type_id,
                    'entity_id' => $entity->id,
                    'effective_from' => $employee->join_date,
                ]);
            }
        });
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
