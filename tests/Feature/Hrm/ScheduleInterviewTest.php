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
use App\Modules\Hrm\Models\QuestionSet;
use App\Modules\Hrm\Models\RejectionReason;
use App\Modules\Hrm\Notifications\InterviewScheduled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use LogicException;
use Tests\Concerns\ActsAsTenantUser;
use Tests\Concerns\MakesInterviewStages;
use Tests\TestCase;

/**
 * Scheduling interview rounds, the panel, and telling the panel.
 */
class ScheduleInterviewTest extends TestCase
{
    use ActsAsTenantUser;
    use MakesInterviewStages;
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

    /** @return array{interview_stage_id: int, scheduled_at: string, location: string} */
    private function details(string $stage = 'Stage 1'): array
    {
        return ['interview_stage_id' => $this->stageFor($this->application, $stage)->id, 'scheduled_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(), 'location' => 'Room 2'];
    }

    public function test_round_one_with_its_panel_moves_the_application_to_interview(): void
    {
        $interview = $this->schedule->handle($this->application, $this->details(), [$this->withLogin->id, $this->withoutLogin->id], 7);

        $this->assertSame(1, $interview->round);
        $this->assertSame(InterviewStatus::Scheduled, $interview->status);
        $this->assertSame([$this->withLogin->id, $this->withoutLogin->id], $interview->panelists()->pluck('employee_id')->all());
        $this->assertSame(ApplicationStage::Interview, $this->application->fresh()->stage);
        $this->assertSame('Stage 1 scheduled', $this->application->stageChanges()->get()->last()->note);
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
        $second = $this->schedule->handle($this->application->fresh(), $this->details(), [$this->withLogin->id]);   // same stage: the first was cancelled
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
            ->assertViewHas('allowed', fn (array $allowed) => $allowed['schedule']->contains((string) $this->application->id));

        Livewire::test(InterviewForm::class)
            ->dispatch('schedule-interview', applicationId: $this->application->id)
            ->assertSee('Rahim')
            ->assertSet('stageId', 'new')
            ->assertSet('stageName', 'Stage 1')
            ->set('questionSetId', QuestionSet::factory()->withQuestions()->create(['name' => 'HR set'])->id)
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

        $interview = $this->application->interviews()->sole();
        $this->assertSame('Room 2', $interview->location);
        $this->assertSame('Stage 1', $interview->title());
        $this->assertSame('HR set', $interview->stage->questionSet->name);
    }

    public function test_a_new_stage_needs_a_unique_name_and_a_question_set(): void
    {
        $this->stageFor($this->application, 'Technical');

        Livewire::test(InterviewForm::class)
            ->dispatch('schedule-interview', applicationId: $this->application->id)
            ->assertSet('stageName', 'Stage 2')   // the posting has one stage already
            ->set('stageName', 'Technical')
            ->call('addPanelist', $this->withLogin->id)
            ->call('save')
            ->assertHasErrors(['stageName', 'questionSetId']);

        $this->assertSame(0, $this->application->interviews()->count());
        $this->assertSame(1, $this->application->jobPosting->interviewStages()->count());   // nothing made
    }

    public function test_an_existing_stage_takes_only_applicants_not_in_it_yet(): void
    {
        $karim = app(ApplyToPosting::class)->handle(Applicant::factory()->create(['first_name' => 'Karim', 'last_name' => 'Ali']), $this->application->jobPosting);
        $stage = $this->stageFor($this->application, 'Stage 1');
        $done = $this->schedule->handle($this->application, $this->details(), [$this->withLogin->id]);
        $done->panelists()->sole()->forceFill(['submitted_at' => now()])->save();
        $done->complete();   // Rahim had Stage 1

        Livewire::test(InterviewForm::class)
            ->dispatch('schedule-interview', applicationIds: [$this->application->id, $karim->id])
            ->set('stageId', (string) $stage->id)
            ->assertSee('Already in this stage: will be skipped')
            ->call('addPanelist', $this->withLogin->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast', variant: 'warning', message: '1 interview scheduled. The panel has been told. Rahim '.$this->application->applicant->last_name.': Already interviewed in Stage 1.');

        $this->assertSame($stage->id, $karim->interviews()->sole()->interview_stage_id);
        $this->assertSame(1, $this->application->interviews()->count());
        $this->assertSame('Already interviewed in Stage 1.', $this->schedule->problem($this->application->fresh(), [$this->withLogin->id], $stage));

        // A stage of another posting cannot be used.
        $other = $this->stageFor(JobPosting::factory()->open()->create(), 'Stage 1');
        $this->assertSame('This stage belongs to another job posting.', $this->schedule->problem($this->application->fresh(), [$this->withLogin->id], $other));
    }

    public function test_several_ticked_applicants_get_their_own_rounds(): void
    {
        $posting = $this->application->jobPosting;
        $karim = app(ApplyToPosting::class)->handle(Applicant::factory()->create(['first_name' => 'Karim', 'last_name' => 'Ali']), $posting);
        $nadia = app(ApplyToPosting::class)->handle(Applicant::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Islam']), $posting);
        $this->schedule->handle($nadia, $this->details(), [$this->withLogin->id]);   // Nadia already has a round planned

        // "Schedule interview" in the toolbar takes only the ticked rows where a round can be planned (not Nadia).
        Livewire::test(ApplicationPipeline::class, ['postingId' => $posting->id])
            ->assertSee('Schedule interview')
            ->assertViewHas('allowed', fn (array $allowed) => $allowed['schedule']->contains((string) $karim->id)
                && ! $allowed['schedule']->contains((string) $nadia->id));

        $form = Livewire::test(InterviewForm::class)
            ->dispatch('schedule-interview', applicationIds: [$this->application->id, $karim->id, $nadia->id])
            ->assertSee('Schedule 3 interviews')
            ->set('questionSetId', QuestionSet::factory()->withQuestions()->create()->id)
            ->set('form.scheduled_at', '2030-01-10T10:00')
            ->set('form.duration_minutes', 45)
            ->assertSee('One after another')
            ->call('save')
            ->assertHasErrors(['panel']);   // no panel: nobody can be scheduled, nothing saved
        $this->assertSame(0, $karim->interviews()->count());

        $form->call('addPanelist', $this->withoutLogin->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast', variant: 'warning', message: '2 interviews scheduled. The panel has been told. Nadia Islam: An interview is already planned. Complete or cancel it first.');

        // Back-to-back: 10:00, then 10:45.
        $this->assertSame('2030-01-10 10:00:00', $this->application->interviews()->sole()->scheduled_at->toDateTimeString());
        $this->assertSame('2030-01-10 10:45:00', $karim->interviews()->sole()->scheduled_at->toDateTimeString());
        $this->assertSame(1, $nadia->interviews()->count());
    }

    public function test_several_applicants_can_also_be_seen_at_the_same_time(): void
    {
        $karim = app(ApplyToPosting::class)->handle(Applicant::factory()->create(), $this->application->jobPosting);

        Livewire::test(InterviewForm::class)
            ->dispatch('schedule-interview', applicationIds: [$this->application->id, $karim->id])
            ->set('questionSetId', QuestionSet::factory()->withQuestions()->create()->id)
            ->set('form.scheduled_at', '2030-01-10T10:00')
            ->set('timing', 'same_time')
            ->call('addPanelist', $this->withLogin->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast', variant: 'success');

        $this->assertSame('2030-01-10 10:00:00', $karim->interviews()->sole()->scheduled_at->toDateTimeString());
        $this->assertSame('2030-01-10 10:00:00', $this->application->interviews()->sole()->scheduled_at->toDateTimeString());
    }
}
