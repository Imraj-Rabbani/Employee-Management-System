<?php

namespace Tests\Feature\Hrm;

use App\Core\Support\TenantUploads;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Actions\ApplyToPosting;
use App\Modules\Hrm\Actions\ScheduleInterview;
use App\Modules\Hrm\Enums\InterviewStatus;
use App\Modules\Hrm\Livewire\Ess\MyInterviews;
use App\Modules\Hrm\Livewire\Recruitment\InterviewScoreSheet;
use App\Modules\Hrm\Livewire\Recruitment\PostingInterviews;
use App\Modules\Hrm\Models\Applicant;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Interview;
use App\Modules\Hrm\Models\InterviewFactor;
use App\Modules\Hrm\Models\InterviewPanelist;
use App\Modules\Hrm\Models\JobPosting;
use App\Modules\Hrm\Models\OrgUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 6.6: score sheets (by the interviewer or HR), My Interviews, and results / ranking. [REC-05]
 */
class InterviewScoreSheetTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $hr;

    private User $salmaLogin;

    private Interview $interview;

    private InterviewPanelist $salma;

    private InterviewFactor $communication;   // 40, required

    private InterviewFactor $technical;       // 60

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->hr = $this->actingAsTenantUser();   // Administrator
        $this->tenant = $this->hr->tenant;

        $this->communication = InterviewFactor::factory()->required()->create(['name' => 'Communication', 'weight' => 40, 'sequence' => 1]);
        $this->technical = InterviewFactor::factory()->create(['name' => 'Technical', 'weight' => 60, 'sequence' => 2]);

        $employee = Employee::factory()->create(['first_name' => 'Salma']);
        $this->salmaLogin = User::factory()->for($this->tenant)->create();
        $employee->forceFill(['user_id' => $this->salmaLogin->id])->save();

        $application = app(ApplyToPosting::class)->handle(
            Applicant::factory()->create(['first_name' => 'Rahim']),
            JobPosting::factory()->open()->create(['title' => 'Sales Officer']),
        );
        $this->interview = app(ScheduleInterview::class)->handle($application, ['scheduled_at' => now()->addDay()->toDateTimeString()], [$employee->id]);
        $this->salma = $this->interview->panelists()->sole();
    }

    public function test_the_interviewer_scores_and_sends_from_my_interviews(): void
    {
        $this->actingAs($this->salmaLogin);

        $this->get(route('ess.interviews'))->assertOk()->assertSee('Rahim')->assertSee('Sales Officer');
        Livewire::test(MyInterviews::class)->assertSee('To do');

        Livewire::test(InterviewScoreSheet::class)
            ->dispatch('open-score-sheet', panelistId: $this->salma->id)
            ->assertSee('Communication')->assertSee('Technical')
            ->set("scores.{$this->communication->id}", '4')
            ->call('saveDraft')
            ->assertHasNoErrors()
            ->set("scores.{$this->technical->id}", '3')
            ->set("notes.{$this->technical->id}", 'Weak on Excel')
            ->set('recommendation', 'consider')
            ->call('send')
            ->assertHasNoErrors();

        $this->salma->refresh();
        $this->assertNotNull($this->salma->submitted_at);
        $this->assertSame('consider', $this->salma->recommendation);
        $this->assertSame('40.00', $this->salma->scores()->where('interview_factor_id', $this->communication->id)->value('weight'));
        $this->assertSame('Weak on Excel', $this->salma->scores()->where('interview_factor_id', $this->technical->id)->value('note'));
        Livewire::test(MyInterviews::class)->assertSee('Sent');
    }

    public function test_sending_needs_required_factors_and_a_recommendation(): void
    {
        $this->actingAs($this->salmaLogin);

        Livewire::test(InterviewScoreSheet::class)
            ->dispatch('open-score-sheet', panelistId: $this->salma->id)
            ->set("scores.{$this->technical->id}", '3')   // Communication (required) missing
            ->call('send')
            ->assertHasErrors(["scores.{$this->communication->id}", 'recommendation'])
            ->set("scores.{$this->technical->id}", '9')   // above the scale
            ->call('saveDraft')
            ->assertHasErrors(["scores.{$this->technical->id}"]);

        $this->assertNull($this->salma->fresh()->submitted_at);
    }

    public function test_a_sent_sheet_is_locked(): void
    {
        $this->salma->scores()->create(['interview_factor_id' => $this->communication->id, 'score' => 4, 'weight' => 40]);
        $this->salma->forceFill(['submitted_at' => now()])->save();
        $this->actingAs($this->salmaLogin);

        Livewire::test(InterviewScoreSheet::class)
            ->dispatch('open-score-sheet', panelistId: $this->salma->id)
            ->assertSee('Sent')
            ->set("scores.{$this->communication->id}", '1')
            ->call('saveDraft')
            ->assertHasErrors(['scores']);

        $this->assertSame(4, $this->salma->scores()->value('score'));
    }

    public function test_hr_may_enter_it_on_their_behalf_but_nobody_else(): void
    {
        Livewire::test(InterviewScoreSheet::class)   // as HR (Administrator)
            ->dispatch('open-score-sheet', panelistId: $this->salma->id)
            ->assertSee('on behalf of');

        // Another employee with a login, not on the panel and not HR: 403.
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: []);
        Livewire::test(InterviewScoreSheet::class)->dispatch('open-score-sheet', panelistId: $this->salma->id)->assertForbidden();

        // HR limited to another org unit: 403 too.
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.recruitment.manage']);
        Role::where('name', 'like', 'Test role%')->latest('id')->firstOrFail()->update(['org_unit_id' => OrgUnit::factory()->create()->id]);
        Livewire::test(InterviewScoreSheet::class)->dispatch('open-score-sheet', panelistId: $this->salma->id)->assertForbidden();
    }

    public function test_the_cv_link_works_only_for_your_own_panel_seat(): void
    {
        $applicant = $this->interview->application->applicant;
        $applicant->forceFill([
            'cv_path' => app(TenantUploads::class)->store(UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf'), 'applicants/cv'),
            'cv_name' => 'Rahim CV.pdf',
        ])->save();

        $this->actingAs($this->salmaLogin);
        $this->get(route('ess.interviews.cv', $this->salma))->assertOk()->assertDownload('Rahim CV.pdf');

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: []);
        $this->get(route('ess.interviews.cv', $this->salma))->assertForbidden();
    }

    public function test_complete_cancel_and_ranking_on_the_posting_page(): void
    {
        $page = Livewire::test(PostingInterviews::class, ['postingId' => $this->interview->application->job_posting_id])
            ->assertSee('Rahim')->assertSee('Salma')
            ->call('complete', $this->interview->id)
            ->assertDispatched('toast', variant: 'danger');   // nobody sent scores yet
        $this->assertSame(InterviewStatus::Scheduled, $this->interview->fresh()->status);

        // Salma sends 4 and 3 → (0.8×40 + 0.6×60) = 68 %
        $this->salma->scores()->create(['interview_factor_id' => $this->communication->id, 'score' => 4, 'weight' => 40]);
        $this->salma->scores()->create(['interview_factor_id' => $this->technical->id, 'score' => 3, 'weight' => 60]);
        $this->salma->forceFill(['submitted_at' => now()])->save();

        $page->call('complete', $this->interview->id)
            ->assertDispatched('toast', variant: 'success')
            ->assertSee('68%')
            ->assertSeeInOrder(['Ranking', '1', 'Rahim', '68%']);
        $this->assertSame(InterviewStatus::Completed, $this->interview->fresh()->status);

        $page->call('cancel', $this->interview->id)->assertDispatched('toast', variant: 'danger');   // completed: not cancelled
    }
}
