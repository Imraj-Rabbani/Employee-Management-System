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
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Hrm\Models\Interview;
use App\Modules\Hrm\Models\InterviewPanelist;
use App\Modules\Hrm\Models\InterviewQuestion;
use App\Modules\Hrm\Models\InterviewStage;
use App\Modules\Hrm\Models\JobPosting;
use App\Modules\Hrm\Models\QuestionSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\Concerns\MakesInterviewStages;
use Tests\TestCase;

/**
 * Score sheets (by the interviewer or HR), My Interviews, and results / ranking.
 */
class InterviewScoreSheetTest extends TestCase
{
    use ActsAsTenantUser;
    use MakesInterviewStages;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $hr;

    private User $salmaLogin;

    private Interview $interview;

    private InterviewPanelist $salma;

    private InterviewQuestion $communication;   // 40 in the stage's set

    private InterviewQuestion $technical;       // 60

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->hr = $this->actingAsTenantUser();   // Administrator
        $this->tenant = $this->hr->tenant;

        $this->communication = InterviewQuestion::factory()->create(['name' => 'Communication', 'sequence' => 1]);
        $this->technical = InterviewQuestion::factory()->create(['name' => 'Technical', 'sequence' => 2]);
        $set = QuestionSet::factory()->withQuestions([$this->communication->id => 40, $this->technical->id => 60])->create();

        $employee = Employee::factory()->create(['first_name' => 'Salma']);
        $this->salmaLogin = User::factory()->for($this->tenant)->create();
        $employee->forceFill(['user_id' => $this->salmaLogin->id])->save();

        $application = app(ApplyToPosting::class)->handle(
            Applicant::factory()->create(['first_name' => 'Rahim']),
            JobPosting::factory()->open()->create(['title' => 'Sales Officer']),
        );
        $stage = $this->stageFor($application, 'Stage 1', $set);
        $this->interview = app(ScheduleInterview::class)->handle($application, ['interview_stage_id' => $stage->id, 'scheduled_at' => now()->addDay()->toDateTimeString()], [$employee->id]);
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
        $this->assertSame('40.00', $this->salma->scores()->where('interview_question_id', $this->communication->id)->value('weight'));
        $this->assertSame('Weak on Excel', $this->salma->scores()->where('interview_question_id', $this->technical->id)->value('note'));
        Livewire::test(MyInterviews::class)->assertSee('Sent');
    }

    public function test_the_interviewer_ticks_several_sheets_and_sends_them_at_once(): void
    {
        $salmaEmployee = $this->salma->employee;
        $second = app(ScheduleInterview::class)->handle(
            app(ApplyToPosting::class)->handle(Applicant::factory()->create(['first_name' => 'Karim', 'last_name' => 'Ali']), $this->interview->application->jobPosting),
            ['interview_stage_id' => $this->interview->interview_stage_id, 'scheduled_at' => now()->addDays(2)->toDateTimeString()], [$salmaEmployee->id],
        )->panelists()->sole();
        $notMine = app(ScheduleInterview::class)->handle(
            app(ApplyToPosting::class)->handle(Applicant::factory()->create(['first_name' => 'Nadia']), $this->interview->application->jobPosting),
            ['interview_stage_id' => $this->interview->interview_stage_id, 'scheduled_at' => now()->addDays(3)->toDateTimeString()], [Employee::factory()->create()->id],
        )->panelists()->sole();
        $this->actingAs($this->salmaLogin);

        // Rahim: complete. Karim: Communication not scored (every question of the set must be).
        Livewire::test(InterviewScoreSheet::class)
            ->dispatch('open-score-sheet', panelistId: $this->salma->id)
            ->assertDontSee('Send scores')->assertSee('Save and close')   // the interviewer sends from the list
            ->set("scores.{$this->communication->id}", '4')
            ->set("scores.{$this->technical->id}", '5')
            ->set('recommendation', 'recommend')
            ->call('saveDraft', true)
            ->assertHasNoErrors();
        Livewire::test(InterviewScoreSheet::class)
            ->dispatch('open-score-sheet', panelistId: $second->id)
            ->set("scores.{$this->technical->id}", '3')
            ->set('recommendation', 'consider')
            ->call('saveDraft');

        Livewire::test(MyInterviews::class)
            ->assertSee('Ready to send')
            ->assertSee('Not scored: Communication.')
            ->set('selected', [(string) $this->salma->id, (string) $second->id, (string) $notMine->id])
            ->call('sendSelected')
            ->assertDispatched('toast', variant: 'warning', message: '1 score sheet sent. Thank you. Karim Ali: Not scored: Communication.')
            ->assertSet('selected', []);

        $this->assertNotNull($this->salma->fresh()?->submitted_at);
        $this->assertNull($second->fresh()?->submitted_at);
        $this->assertNull($notMine->fresh()?->submitted_at);   // someone else's sheet is never touched

        // HR entering a sheet on someone's behalf still sends it from the window.
        $this->actingAs($this->hr);
        Livewire::test(InterviewScoreSheet::class)->dispatch('open-score-sheet', panelistId: $second->id)->assertSee('Send scores');
    }

    public function test_sending_needs_every_question_and_a_recommendation(): void
    {
        $this->actingAs($this->salmaLogin);

        Livewire::test(InterviewScoreSheet::class)
            ->dispatch('open-score-sheet', panelistId: $this->salma->id)
            ->set("scores.{$this->technical->id}", '3')   // Communication missing
            ->call('send')
            ->assertHasErrors(["scores.{$this->communication->id}", 'recommendation'])
            ->set("scores.{$this->technical->id}", '9')   // above the scale
            ->call('saveDraft')
            ->assertHasErrors(["scores.{$this->technical->id}"]);

        $this->assertNull($this->salma->fresh()->submitted_at);
    }

    public function test_a_sent_sheet_is_locked(): void
    {
        $this->salma->scores()->create(['interview_question_id' => $this->communication->id, 'score' => 4, 'weight' => 40]);
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

        // HR limited to another entity: 403 too.
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.recruitment.manage']);
        Role::where('name', 'like', 'Test role%')->latest('id')->firstOrFail()->update(['entity_id' => Entity::factory()->create()->id]);
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
        $this->salma->scores()->create(['interview_question_id' => $this->communication->id, 'score' => 4, 'weight' => 40]);
        $this->salma->scores()->create(['interview_question_id' => $this->technical->id, 'score' => 3, 'weight' => 60]);
        $this->salma->forceFill(['submitted_at' => now()])->save();

        $page->call('complete', $this->interview->id)
            ->assertDispatched('toast', variant: 'success')
            ->assertSee('68%')
            ->assertSeeInOrder(['Ranking', '1', 'Rahim', '68%']);
        $this->assertSame(InterviewStatus::Completed, $this->interview->fresh()->status);

        $page->call('cancel', $this->interview->id)->assertDispatched('toast', variant: 'danger');   // completed: not cancelled
    }

    public function test_the_ranking_shows_each_stage_and_the_sum_and_sorts_by_any_column(): void
    {
        $posting = $this->interview->application->jobPosting;
        $karima = app(ApplyToPosting::class)->handle(Applicant::factory()->create(['first_name' => 'Karima', 'last_name' => 'Ali']), $posting);
        $stage2 = $this->stageFor($posting, 'Stage 2', QuestionSet::factory()->withQuestions([$this->technical->id => 100])->create());

        // Rahim: Stage 1 = 68 %, Stage 2 = 100 %. Karima: Stage 1 only = 80 %.
        $this->sentAndCompleted($this->interview, [4, 3]);
        $this->sentAndCompleted($this->scheduled($this->interview->application->fresh(), $stage2), [null, 5]);
        $this->sentAndCompleted($this->scheduled($karima, $this->interview->stage), [4, 4]);

        $page = Livewire::test(PostingInterviews::class, ['postingId' => $posting->id])
            ->assertSee('Stage 1')->assertSee('Stage 2')
            ->assertSeeInOrder(['Rank', 'Applicant', 'Stage 1', 'Stage 2', 'Total'])
            ->assertSeeInOrder(['Rahim', '68%', '100%', '168'])     // the sum, not the average
            ->assertSeeInOrder(['Karima', '80%', '—', '80']);
        $page->assertViewHas('ranking', fn ($rows) => $rows->pluck('application.applicant.first_name')->all() === ['Rahim', 'Karima']);

        $page->call('sortRanking', 'stage-'.$this->interview->interview_stage_id)   // Stage 1, best first
            ->assertViewHas('ranking', fn ($rows) => $rows->pluck('application.applicant.first_name')->all() === ['Karima', 'Rahim'])
            ->call('sortRanking', 'stage-'.$stage2->id)                       // no Stage 2 score: last
            ->assertViewHas('ranking', fn ($rows) => $rows->pluck('application.applicant.first_name')->all() === ['Rahim', 'Karima'])
            ->call('sortRanking', 'total')->call('sortRanking', 'total')     // total, lowest first
            ->assertViewHas('ranking', fn ($rows) => $rows->pluck('application.applicant.first_name')->all() === ['Karima', 'Rahim']);

        // Interviews card: tabs per stage.
        $page->assertSeeInOrder(['All', '3', 'Stage 1', '2', 'Stage 2', '1'])
            // Each tab button selects ITS stage (the ids, not their positions).
            ->assertSeeHtml("\$set('stageTab', '".$this->interview->interview_stage_id."')\" wire:key=\"stage-tab-".$this->interview->interview_stage_id."\"")
            ->assertSeeHtml("\$set('stageTab', '".$stage2->id."')\" wire:key=\"stage-tab-".$stage2->id."\"")
            ->set('stageTab', (string) $stage2->id)
            ->assertViewHas('interviews', fn ($list) => $list->count() === 1);
    }

    private function scheduled(Application $application, InterviewStage $stage): Interview
    {
        return app(ScheduleInterview::class)->handle($application, ['interview_stage_id' => $stage->id, 'scheduled_at' => now()->addDays(4)->toDateTimeString()], [$this->salma->employee_id]);
    }

    /** @param  array{0: ?int, 1: ?int}  $scores  Communication, Technical (null = not on this stage's sheet) */
    private function sentAndCompleted(Interview $interview, array $scores): void
    {
        $seat = $interview->panelists()->firstOrFail();
        $items = $interview->stage->questionSet->items->keyBy('interview_question_id');
        foreach ([$this->communication->id => $scores[0], $this->technical->id => $scores[1]] as $questionId => $score) {
            if ($score !== null) {
                $seat->scores()->create(['interview_question_id' => $questionId, 'score' => $score, 'weight' => $items[$questionId]->weight]);
            }
        }
        $seat->forceFill(['submitted_at' => now()])->save();
        $interview->complete();
    }
}
