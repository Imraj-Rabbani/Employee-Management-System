<?php

namespace App\Modules\Hrm\Database\Factories;

use App\Modules\Hrm\Enums\InterviewStatus;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\Interview;
use App\Modules\Hrm\Models\InterviewStage;
use App\Modules\Hrm\Models\QuestionSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake interviews for tests: Interview::factory()->for($application)->create(['round' => 2]).
 * Without a stage, a new stage "Round N" of the application's posting is made (with a 2-question set).
 * Skips the scheduling rules; add panelists yourself.
 *
 * @extends Factory<Interview>
 */
class InterviewFactory extends Factory
{
    protected $model = Interview::class;

    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'interview_stage_id' => fn (array $attributes) => InterviewStage::firstOrCreate(
                ['job_posting_id' => Application::findOrFail($attributes['application_id'])->job_posting_id, 'name' => 'Round '.($attributes['round'] ?? 1)],
                ['question_set_id' => QuestionSet::factory()->withQuestions()->create()->id],
            )->id,
            'round' => 1,
            'scheduled_at' => now()->addDays(3)->setTime(10, 0),
            'duration_minutes' => 30,
            'location' => 'Meeting room 2',
            'status' => InterviewStatus::Scheduled,
        ];
    }

    public function completed(): static
    {
        return $this->state(['status' => InterviewStatus::Completed]);
    }
}
