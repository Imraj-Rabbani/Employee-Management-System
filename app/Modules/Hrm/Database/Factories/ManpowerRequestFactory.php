<?php

namespace App\Modules\Hrm\Database\Factories;

use App\Modules\Hrm\Enums\ManpowerRequestType;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Hrm\Models\ManpowerRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake manpower requests for tests and demo data.
 *
 *     ManpowerRequest::factory()->create()                        new seats, draft (not submitted)
 *     ManpowerRequest::factory()->replacing($employee)->create()
 *     ManpowerRequest::factory()->approved()->create()            approved_at set (without a workflow)
 *
 * @extends Factory<ManpowerRequest>
 */
class ManpowerRequestFactory extends Factory
{
    protected $model = ManpowerRequest::class;

    public function definition(): array
    {
        $min = fake()->randomElement([25000, 40000, 60000]);

        return [
            'entity_id' => Entity::factory(),
            'designation_id' => Designation::factory(),
            'type' => ManpowerRequestType::New,
            'headcount' => fake()->numberBetween(1, 3),
            'salary_min' => $min,
            'salary_max' => $min + 10000,
            'needed_by' => now()->addMonths(2)->toDateString(),
            'justification' => fake()->sentence(),
        ];
    }

    /** A replacement for $employee, in one of their current entities (the first one). */
    public function replacing(Employee $employee): static
    {
        return $this->state(fn () => [
            'type' => ManpowerRequestType::Replacement,
            'replaces_employee_id' => $employee->id,
            'entity_id' => $employee->entityIds()[0] ?? Entity::factory(),
            'headcount' => 1,
        ]);
    }

    public function approved(): static
    {
        return $this->afterCreating(fn (ManpowerRequest $request) => $request->forceFill(['approved_at' => now()])->save());
    }
}
