<?php

namespace Tests\Feature\Hrm;

use App\Modules\Hrm\Enums\InterviewStatus;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Interview;
use App\Modules\Hrm\Models\InterviewFactor;
use App\Modules\Hrm\Models\InterviewPanelist;
use App\Modules\Hrm\Models\JobPosting;
use App\Modules\Hrm\Services\InterviewScoring;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 6.6: interview tables and the scoring maths. [REC-05]
 * Numbers are small on purpose: each expected % can be checked by hand.
 */
class InterviewScoringTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private InterviewScoring $scoring;

    private InterviewFactor $communication;   // weight 40

    private InterviewFactor $technical;       // weight 60

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsTenantUser();
        $this->scoring = app(InterviewScoring::class);
        $this->communication = InterviewFactor::factory()->create(['name' => 'Communication', 'weight' => 40]);
        $this->technical = InterviewFactor::factory()->create(['name' => 'Technical', 'weight' => 60]);
    }

    /**
     * A panelist who scored [factor id => score], submitted unless told otherwise.
     *
     * @param  array<int, int>  $scores
     */
    private function panelist(Interview $interview, array $scores, bool $submitted = true): InterviewPanelist
    {
        $panelist = $interview->panelists()->create(['employee_id' => Employee::factory()->create()->id]);
        foreach ($scores as $factorId => $score) {
            $panelist->scores()->create([
                'interview_factor_id' => $factorId,
                'score' => $score,
                'weight' => InterviewFactor::findOrFail($factorId)->weight,
            ]);
        }
        if ($submitted) {
            $panelist->forceFill(['submitted_at' => now()])->save();
        }

        return $panelist->load('scores');
    }

    public function test_one_panelist_weighted_result(): void
    {
        $interview = Interview::factory()->create();

        // (4/5 × 40 + 3/5 × 60) / 100 = (32 + 36) / 100 = 68 %
        $panelist = $this->panelist($interview, [$this->communication->id => 4, $this->technical->id => 3]);

        $this->assertSame(68.0, $this->scoring->panelist($panelist));
    }

    public function test_zero_weights_mean_every_factor_counts_the_same(): void
    {
        $this->communication->update(['weight' => 0]);
        $this->technical->update(['weight' => 0]);

        // (4/5 + 3/5) / 2 = 70 %
        $panelist = $this->panelist(Interview::factory()->create(), [$this->communication->id => 4, $this->technical->id => 3]);

        $this->assertSame(70.0, $this->scoring->panelist($panelist));
    }

    public function test_the_weight_is_kept_from_the_time_of_scoring(): void
    {
        $panelist = $this->panelist(Interview::factory()->create(), [$this->communication->id => 4, $this->technical->id => 3]);

        $this->communication->update(['weight' => 90]);   // HR changes the setup later
        $this->technical->update(['weight' => 10]);

        $this->assertSame(68.0, $this->scoring->panelist($panelist->fresh('scores')));
    }

    public function test_the_round_is_the_average_of_submitted_panelists_only(): void
    {
        $interview = Interview::factory()->create();
        $this->panelist($interview, [$this->communication->id => 4, $this->technical->id => 3]);   // 68
        $this->panelist($interview, [$this->communication->id => 5, $this->technical->id => 5]);   // 100
        $this->panelist($interview, [$this->communication->id => 1, $this->technical->id => 1], submitted: false);   // not counted

        $this->assertSame(84.0, $this->scoring->interview($interview->load('panelists.scores')));

        $interview->update(['status' => InterviewStatus::Cancelled]);
        $this->assertNull($this->scoring->interview($interview));
    }

    public function test_the_application_averages_its_completed_rounds(): void
    {
        $application = Application::factory()->create();
        $first = Interview::factory()->for($application)->completed()->create(['round' => 1]);
        $second = Interview::factory()->for($application)->completed()->create(['round' => 2]);
        $third = Interview::factory()->for($application)->create(['round' => 3]);   // still scheduled: not counted
        $this->panelist($first, [$this->communication->id => 4, $this->technical->id => 3]);    // 68
        $this->panelist($second, [$this->communication->id => 5, $this->technical->id => 4]);   // (40 + 48) = 88
        $this->panelist($third, [$this->communication->id => 1, $this->technical->id => 1]);

        $this->assertSame(78.0, $this->scoring->application($application->load('interviews.panelists.scores')));
    }

    public function test_ranking_best_first_with_shared_ranks(): void
    {
        $posting = JobPosting::factory()->open()->create();
        $scores = ['A' => [5, 5], 'B' => [4, 3], 'C' => [4, 3], 'D' => [2, 2]];   // 100, 68, 68, 40
        foreach ($scores as $name => [$c, $t]) {
            $application = Application::factory()->for($posting)->create();
            $application->applicant->update(['first_name' => $name]);
            $this->panelist(Interview::factory()->for($application)->completed()->create(), [$this->communication->id => $c, $this->technical->id => $t]);
        }
        Application::factory()->for($posting)->create();   // never interviewed: not ranked

        $ranking = $this->scoring->ranking($posting);

        $this->assertSame([100.0, 68.0, 68.0, 40.0], $ranking->pluck('score')->all());
        $this->assertSame([1, 2, 2, 4], $ranking->pluck('rank')->all());
        $this->assertSame('A', $ranking->first()['application']->applicant->first_name);
    }

    public function test_one_round_number_per_application_and_one_seat_per_employee(): void
    {
        $interview = Interview::factory()->create(['round' => 1]);
        $employee = Employee::factory()->create();
        $interview->panelists()->create(['employee_id' => $employee->id]);

        try {
            $interview->panelists()->create(['employee_id' => $employee->id]);
            $this->fail('The same employee was put on the panel twice.');
        } catch (QueryException) {
        }

        $this->expectException(QueryException::class);
        Interview::factory()->for($interview->application)->create(['round' => 1]);
    }
}
