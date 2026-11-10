<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Hrm\Actions\ApplyToPosting;
use App\Modules\Hrm\Actions\MoveApplication;
use App\Modules\Hrm\Actions\ScheduleInterview;
use App\Modules\Hrm\Enums\ApplicationStage;
use App\Modules\Hrm\Enums\EmployeeStatus;
use App\Modules\Hrm\Enums\InterviewStatus;
use App\Modules\Hrm\Livewire\Recruitment\ApplicationPipeline;
use App\Modules\Hrm\Livewire\Recruitment\InterviewForm;
use App\Modules\Hrm\Models\Applicant;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\JobPosting;
use App\Modules\Hrm\Models\RejectionReason;
use App\Modules\Hrm\Notifications\InterviewScheduled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use LogicException;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 6.6: scheduling interview rounds, the panel, and telling the panel. [REC-05]
 */
class ScheduleInterviewTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private ScheduleInterview $schedule;

    private Application $application;

    private Employee $withLogin;

    private Employee $withoutLogin;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->tenant = $this->actingAsTenantUser()->tenant;
        $this->schedule = app(ScheduleInterview::class);

        $this->application = app(ApplyToPosting::class)->handle(
            Applicant::factory()->create(['first_name' => 'Rahim']),
            JobPosting::factory()->open()->create(),
        );

        $this->withLogin = Employee::factory()->create(['first_name' => 'Salma']);
        $this->withLogin->forceFill(['user_id' => User::factory()->for($this->tenant)->create()->id])->save();
        $this->withoutLogin = Employee::factory()->create(['first_name' => 'Karim', 'office_email' => 'karim@company.test']);
    }

    /** @return array{scheduled_at: string, location: string} */
    private function details(): array
    {
        return ['scheduled_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(), 'location' => 'Room 2'];
    }

    public function test_round_one_with_its_panel_moves_the_application_to_interview(): void
    {
        $interview = $this->schedule->handle($this->application, $this->details(), [$this->withLogin->id, $this->withoutLogin->id], 7);

        $this->assertSame(1, $interview->round);
        $this->assertSame(InterviewStatus::Scheduled, $interview->status);
        $this->assertSame([$this->withLogin->id, $this->withoutLogin->id], $interview->panelists()->pluck('employee_id')->all());
        $this->assertSame(ApplicationStage::Interview, $this->application->fresh()->stage);
        $this->assertSame('Round 1 scheduled', $this->application->stageChanges()->get()->last()->note);
    }

    public function test_the_panel_is_told_in_the_bell_and_by_email(): void
    {
        $this->schedule->handle($this->application, $this->details(), [$this->withLogin->id, $this->withoutLogin->id]);

        // With a login: bell + email to the login.
        Notification::assertSentTo(User::findOrFail($this->withLogin->user_id), InterviewScheduled::class,
            fn (InterviewScheduled $n, array $channels) => $n->applicantName === 'Rahim '.$this->application->applicant->last_name
                && $channels === ['mail', 'database'] && $n->url === route('ess.interviews'));

        // Without a login: email only, to the office email, no link.
        Notification::assertSentOnDemand(InterviewScheduled::class,
            fn (InterviewScheduled $n, array $channels, AnonymousNotifiable $to) => $to->routes['mail'] === 'karim@company.test'
                && $channels === ['mail'] && $n->url === null);
    }

    public function test_rounds_count_up_and_only_one_is_planned_at_a_time(): void
    {
        $first = $this->schedule->handle($this->application, $this->details(), [$this->withLogin->id]);

        $this->assertStringContainsString('already planned', (string) $this->schedule->problem($this->application, [$this->withLogin->id]));

        $first->cancel();
        $second = $this->schedule->handle($this->application->fresh(), $this->details(), [$this->withLogin->id]);
        $this->assertSame(2, $second->round);   // the cancelled round keeps number 1
        $this->assertSame(ApplicationStage::Interview, $this->application->fresh()->stage);
        $this->assertSame(2, $this->application->stageChanges()->count());   // moved to Interview only once
    }

    public function test_panel_rules(): void
    {
        $left = Employee::factory()->create(['status' => EmployeeStatus::Separated]);

        $this->assertSame('Add at least one interviewer.', $this->schedule->problem($this->application, []));
        $this->assertSame('Interviewers must be active employees.', $this->schedule->problem($this->application, [$left->id]));

        app(MoveApplication::class)->handle($this->application, ApplicationStage::Rejected, RejectionReason::factory()->create()->id);
        $this->expectException(LogicException::class);
        $this->schedule->handle($this->application->fresh(), $this->details(), [$this->withLogin->id]);
    }

    public function test_changing_tells_new_panelists_and_time_changes_to_the_rest(): void
    {
        $interview = $this->schedule->handle($this->application, $this->details(), [$this->withLogin->id]);
        Notification::fake();   // forget the first invitations

        // Same time, Karim added: only Karim hears about it.
        $this->schedule->update($interview, $this->details(), [$this->withLogin->id, $this->withoutLogin->id]);
        Notification::assertNothingSentTo(User::findOrFail($this->withLogin->user_id));
        Notification::assertSentOnDemandTimes(InterviewScheduled::class, 1);

        // New time: both hear "changed".
        $this->schedule->update($interview, ['scheduled_at' => now()->addDays(3)->toDateTimeString(), 'location' => 'Room 2'], [$this->withLogin->id, $this->withoutLogin->id]);
        Notification::assertSentTo(User::findOrFail($this->withLogin->user_id), InterviewScheduled::class, fn (InterviewScheduled $n) => $n->changed);
    }

    public function test_someone_who_sent_scores_stays_on_the_panel(): void
    {
        $interview = $this->schedule->handle($this->application, $this->details(), [$this->withLogin->id, $this->withoutLogin->id]);
        $interview->panelists()->where('employee_id', $this->withLogin->id)->firstOrFail()->forceFill(['submitted_at' => now()])->save();

        $this->assertStringContainsString('cannot be removed', (string) $this->schedule->problemForUpdate($interview, [$this->withoutLogin->id]));
        $this->assertNull($this->schedule->problemForUpdate($interview, [$this->withLogin->id]));   // removing the other one is fine
    }

    public function test_the_form_from_the_pipeline(): void
    {
        Livewire::test(ApplicationPipeline::class, ['postingId' => $this->application->job_posting_id])
            ->assertSeeHtml("schedule-interview', { applicationId: {$this->application->id} }");

        Livewire::test(InterviewForm::class)
            ->dispatch('schedule-interview', applicationId: $this->application->id)
            ->assertSee('Rahim')
            ->call('save')
            ->assertHasErrors(['panel'])
            ->set('search', 'Salm')
            ->assertSee('Salma')
            ->call('addPanelist', $this->withLogin->id)
            ->assertSet('search', '')
            ->set('form.location', 'Room 2')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('interview-saved');

        $this->assertSame('Room 2', $this->application->interviews()->sole()->location);
    }
}
