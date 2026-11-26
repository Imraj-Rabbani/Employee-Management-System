<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Modules\Hrm\Livewire\Recruitment\InterviewQuestionForm;
use App\Modules\Hrm\Livewire\Recruitment\QuestionSetForm;
use App\Modules\Hrm\Livewire\Recruitment\RejectionReasonForm;
use App\Modules\Hrm\Models\InterviewQuestion;
use App\Modules\Hrm\Models\JobPosting;
use App\Modules\Hrm\Models\QuestionSet;
use App\Modules\Hrm\Models\RejectionReason;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\Concerns\MakesInterviewStages;
use Tests\TestCase;

/**
 * HRM → Recruitment → Config: interview questions, question sets, rejection reasons.
 */
class RecruitmentConfigTest extends TestCase
{
    use ActsAsTenantUser;
    use MakesInterviewStages;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createMigratedTenant();
        $this->actingAsTenantUser(tenant: $this->tenant);   // Administrator
    }

    public function test_the_tabs_open_and_need_the_permission(): void
    {
        InterviewQuestion::factory()->create(['name' => 'Explains OOP clearly']);
        QuestionSet::factory()->create(['name' => 'Technical – Software']);
        RejectionReason::factory()->create(['name' => 'Did not attend']);

        // Config is a tab of HRM → Recruitment (config/sections.php).
        $this->get('/hrm/recruitment/requests')->assertSee(route('hrm.recruitment.config.questions'))->assertSee('Config');
        $this->get('/hrm/recruitment/config/questions')->assertOk()->assertSee('Interview questions')->assertSee('Question sets')->assertSee('Explains OOP clearly');
        $this->get('/hrm/recruitment/config/sets')->assertOk()->assertSee('Technical – Software');
        $this->get('/hrm/recruitment/config/reasons')->assertOk()->assertSee('Did not attend');

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.recruitment.manage']);
        $this->get('/hrm/recruitment/config/questions')->assertForbidden();
    }

    public function test_models_live_in_the_tenant_database_and_names_are_unique(): void
    {
        foreach ([InterviewQuestion::class, QuestionSet::class, RejectionReason::class] as $model) {
            $row = $model::factory()->create();
            $this->assertSame('tenant', $row->getConnectionName(), $model);
            $this->assertTrue($row->fresh()->is_active, $model.' should be active by default');
        }

        InterviewQuestion::factory()->create(['name' => 'Attitude', 'sequence' => 2]);
        InterviewQuestion::factory()->create(['name' => 'Coding', 'sequence' => 1]);
        $this->assertSame(['Coding', 'Attitude'], InterviewQuestion::ordered()->whereIn('name', ['Coding', 'Attitude'])->pluck('name')->all());

        $this->expectException(QueryException::class);
        QuestionSet::create(['name' => QuestionSet::first()->name]);
    }

    public function test_a_question_is_created_edited_and_kept_while_a_set_uses_it(): void
    {
        Livewire::test(InterviewQuestionForm::class)
            ->dispatch('create-interview-question')
            ->set('form.name', 'Explains OOP clearly')
            ->set('form.description', 'A 5 gives real examples')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('interview-question-saved');

        $question = InterviewQuestion::sole();
        QuestionSet::factory()->withQuestions([$question->id => 100])->create();

        Livewire::test(InterviewQuestionForm::class)
            ->dispatch('edit-interview-question', id: $question->id)
            ->assertSet('form.name', 'Explains OOP clearly')
            ->call('delete')
            ->assertHasErrors(['form.name']);
        $this->assertNotNull($question->fresh());
    }

    public function test_a_set_needs_questions_whose_weights_add_up_to_100(): void
    {
        [$oop, $sql, $comm] = InterviewQuestion::factory()->count(3)->create()->all();

        $form = Livewire::test(QuestionSetForm::class)
            ->dispatch('create-question-set')
            ->set('form.name', 'Technical – Software')
            ->call('save')
            ->assertHasErrors(['items'])                       // no questions
            ->set('addQuestionId', $oop->id)
            ->set('addQuestionId', $sql->id)
            ->set('addQuestionId', $oop->id)                   // already in: not added twice
            ->assertCount('items', 2)
            ->set('items.0.weight', 60)
            ->set('items.1.weight', 30)
            ->assertSee('Total: 90%')
            ->call('save')
            ->assertHasErrors(['items']);                      // 90 %

        $form->set('addQuestionId', $comm->id)
            ->call('splitEqually')
            ->assertSet('items.2.weight', 33.34)
            ->call('moveItem', 2, -1)                          // Communication before SQL
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('question-set-saved');

        $set = QuestionSet::sole()->load('items');
        $this->assertSame([$oop->id, $comm->id, $sql->id], $set->items->pluck('interview_question_id')->all());
        $this->assertSame(100.0, $set->totalWeight());
    }

    public function test_a_set_a_stage_uses_is_locked_and_cannot_be_deleted(): void
    {
        $question = InterviewQuestion::factory()->create();
        $set = QuestionSet::factory()->withQuestions([$question->id => 100])->create(['name' => 'HR']);
        $this->stageFor(JobPosting::factory()->open()->create(), 'Stage 1', $set);

        Livewire::test(QuestionSetForm::class)
            ->dispatch('edit-question-set', id: $set->id)
            ->assertSee('cannot change')
            ->call('removeItem', 0)
            ->assertCount('items', 1)                          // locked: nothing removed
            ->set('form.name', 'HR round')
            ->call('save')
            ->assertHasNoErrors()                              // the name may still change
            ->call('delete')
            ->assertHasErrors(['form.name']);

        $this->assertSame('HR round', $set->fresh()->name);
        $this->assertSame(1, $set->items()->count());
    }

    public function test_a_reason_is_created_and_names_are_unique(): void
    {
        RejectionReason::factory()->create(['name' => 'Did not attend']);

        Livewire::test(RejectionReasonForm::class)
            ->dispatch('create-rejection-reason')
            ->set('form.name', 'Did not attend')
            ->call('save')
            ->assertHasErrors(['form.name'])
            ->set('form.name', 'Salary expectation too high')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('rejection-reason-saved');

        $this->assertSame(2, RejectionReason::count());
    }
}
