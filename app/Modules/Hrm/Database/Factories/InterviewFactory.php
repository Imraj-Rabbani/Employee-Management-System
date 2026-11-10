<?php

namespace App\Modules\Hrm\Database\Factories;

use App\Modules\Hrm\Enums\InterviewStatus;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\Interview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake interviews for tests: Interview::factory()->for($application)->create(['round' => 2]).
 * Skips the scheduling rules (6.6b); add panelists yourself.
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
