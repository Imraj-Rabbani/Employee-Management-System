<?php

namespace App\Modules\Hrm\Database\Factories;

use App\Modules\Hrm\Enums\ApplicationStage;
use App\Modules\Hrm\Models\Applicant;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\JobPosting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake applications for tests. Skips the ApplyToPosting rules (open posting, once per posting):
 * use the action itself when a test is ABOUT those rules.
 *
 *     Application::factory()->create()                          new applicant → new open posting
 *     Application::factory()->for($posting)->create()
 *
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    public function definition(): array
    {
        return [
            'applicant_id' => Applicant::factory(),
            'job_posting_id' => JobPosting::factory()->open(),
            'stage' => ApplicationStage::Applied,
            'applied_on' => today()->toDateString(),
        ];
    }
}
