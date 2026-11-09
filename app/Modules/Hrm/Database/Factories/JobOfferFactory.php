<?php

namespace App\Modules\Hrm\Database\Factories;

use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\JobOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake job offers for tests (a draft; skips the MakeOffer rules).
 *
 *     JobOffer::factory()->for($application)->create(['gross_salary' => 40000])
 *     JobOffer::factory()->approved()->create()    approved_at set, without a workflow
 *
 * @extends Factory<JobOffer>
 */
class JobOfferFactory extends Factory
{
    protected $model = JobOffer::class;

    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'gross_salary' => 40000,
            'designation_id' => Designation::factory(),
            'joining_date' => today()->addMonth()->toDateString(),
            'expires_on' => today()->addWeek()->toDateString(),
        ];
    }

    public function approved(): static
    {
        return $this->afterCreating(fn (JobOffer $offer) => $offer->forceFill(['approved_at' => now()])->save());
    }
}
