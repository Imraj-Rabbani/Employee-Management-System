<?php

namespace App\Modules\Hrm\Database\Factories;

use App\Modules\Hrm\Enums\JobPostingStatus;
use App\Modules\Hrm\Models\JobPosting;
use App\Modules\Hrm\Models\ManpowerRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake job postings for tests and demo data.
 *
 *     JobPosting::factory()->create()            draft, for a new APPROVED manpower request
 *     JobPosting::factory()->open()->create()    open since today, closes in a month
 *     JobPosting::factory()->for($request)->create()
 *
 * @extends Factory<JobPosting>
 */
class JobPostingFactory extends Factory
{
    protected $model = JobPosting::class;

    public function definition(): array
    {
        return [
            'manpower_request_id' => ManpowerRequest::factory()->approved(),
            'title' => fake()->jobTitle(),
            'description' => fake()->paragraph(),
            'requirements' => fake()->paragraph(),
            'openings' => 1,
            'open_date' => today()->toDateString(),
            'close_date' => today()->addMonth()->toDateString(),
            'status' => JobPostingStatus::Draft,
        ];
    }

    public function open(): static
    {
        return $this->state(['status' => JobPostingStatus::Open]);
    }
}
