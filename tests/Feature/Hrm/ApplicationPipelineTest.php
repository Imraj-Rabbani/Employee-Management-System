<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Actions\ApplyToPosting;
use App\Modules\Hrm\Actions\MoveApplication;
use App\Modules\Hrm\Enums\ApplicationStage;
use App\Modules\Hrm\Livewire\Recruitment\ApplicationPipeline;
use App\Modules\Hrm\Models\Applicant;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Hrm\Models\JobPosting;
use App\Modules\Hrm\Models\RejectionReason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * The pipeline on a job posting's page (tabs per stage, Move with note / reason).
 */
class ApplicationPipelineTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private JobPosting $posting;

    private Application $rahim;

    private Application $karima;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->actingAsTenantUser()->tenant;   // Administrator
        $this->posting = JobPosting::factory()->open()->create();

        $apply = app(ApplyToPosting::class);
        $this->rahim = $apply->handle(Applicant::factory()->create(['first_name' => 'Rahim']), $this->posting);
        $this->karima = $apply->handle(Applicant::factory()->create(['first_name' => 'Karima']), $this->posting);
    }

    public function test_tabs_group_by_stage_with_counts(): void
    {
        app(MoveApplication::class)->handle($this->karima, ApplicationStage::Rejected, RejectionReason::factory()->create(['name' => 'Too junior'])->id);

        Livewire::test(ApplicationPipeline::class, ['postingId' => $this->posting->id])
            ->assertSee('Rahim')->assertDontSee('Karima')          // "In progress": closed ones hidden
            ->set('tab', 'rejected')
            ->assertSee('Karima')->assertSee('Too junior')->assertDontSee('Rahim')
            ->set('tab', 'all')
            ->assertSee('Rahim')->assertSee('Karima')
            ->assertSeeInOrder(['In progress', '1', 'Applied', '1']);
    }

    public function test_move_with_a_note(): void
    {
        Livewire::test(ApplicationPipeline::class, ['postingId' => $this->posting->id])
            ->set('selected', [(string) $this->rahim->id])
            ->call('startMove')
            ->assertSee('Move Rahim')
            ->assertSee('Shortlisted')
            ->set('to', 'shortlisted')
            ->set('note', 'Strong CV')
            ->call('move')
            ->assertHasNoErrors()
            ->assertDispatched('toast', variant: 'success');

        $this->assertSame(ApplicationStage::Shortlisted, $this->rahim->fresh()->stage);
        $this->assertSame('Strong CV', $this->rahim->stageChanges()->get()->last()->note);
    }

    public function test_reject_asks_for_a_reason(): void
    {
        $reason = RejectionReason::factory()->create(['name' => 'Salary too high']);

        Livewire::test(ApplicationPipeline::class, ['postingId' => $this->posting->id])
            ->set('selected', [(string) $this->rahim->id])
            ->call('startMove')
            ->assertDontSee('Salary too high')     // the reason box only appears for reject / withdraw
            ->set('to', 'rejected')
            ->assertSee('Salary too high')
            ->call('move')
            ->assertHasErrors(['reasonId'])
            ->set('reasonId', $reason->id)
            ->call('move')
            ->assertHasNoErrors();

        $this->assertSame($reason->id, $this->rahim->fresh()->rejection_reason_id);
    }

    public function test_stages_the_rules_do_not_allow_are_refused(): void
    {
        app(MoveApplication::class)->handle($this->rahim, ApplicationStage::Interview);

        Livewire::test(ApplicationPipeline::class, ['postingId' => $this->posting->id])
            ->set('selected', [(string) $this->rahim->id])
            ->call('startMove')
            ->set('to', 'shortlisted')   // backwards
            ->call('move')
            ->assertHasErrors(['to'])
            ->set('to', 'hired')         // only by the hire
            ->call('move')
            ->assertHasErrors(['to']);

        $this->assertSame(ApplicationStage::Interview, $this->rahim->fresh()->stage);
    }

    public function test_closed_applications_have_no_move_button(): void
    {
        app(MoveApplication::class)->handle($this->rahim, ApplicationStage::Withdrawn, RejectionReason::factory()->create()->id);

        Livewire::test(ApplicationPipeline::class, ['postingId' => $this->posting->id, 'tab' => 'withdrawn'])
            ->assertSee('Rahim')
            ->assertViewHas('allowed', fn (array $allowed) => ! $allowed['move']->contains((string) $this->rahim->id)
                && ! $allowed['schedule']->contains((string) $this->rahim->id) && ! $allowed['offer']->contains((string) $this->rahim->id));
    }

    public function test_several_ticked_applicants_move_together(): void
    {
        $karima = app(ApplyToPosting::class)->handle(Applicant::factory()->create(['first_name' => 'Karima', 'last_name' => 'Ali']), $this->posting);
        app(MoveApplication::class)->handle($karima, ApplicationStage::Interview);

        // Rahim (Applied) + Karima (Interview): only the stages BOTH may go to are offered.
        Livewire::test(ApplicationPipeline::class, ['postingId' => $this->posting->id])
            ->assertSee('History')->assertSee('Schedule interview')->assertSee('Make offer')
            ->set('selected', [(string) $this->rahim->id, (string) $karima->id])
            ->call('startMove')
            ->assertSee('Move 2 applicants')
            ->assertViewHas('targets', fn (array $targets) => ! isset($targets['shortlisted']) && isset($targets['rejected']))
            ->set('to', 'rejected')
            ->set('reasonId', RejectionReason::factory()->create()->id)
            ->call('move')
            ->assertHasNoErrors()
            ->assertDispatched('toast', variant: 'success')
            ->assertSet('selected', []);

        $this->assertSame(ApplicationStage::Rejected, $this->rahim->fresh()->stage);
        $this->assertSame(ApplicationStage::Rejected, $karima->fresh()->stage);
    }

    public function test_ticked_rows_with_no_stage_in_common_cannot_be_moved(): void
    {
        $karima = app(ApplyToPosting::class)->handle(Applicant::factory()->create(), $this->posting);
        app(MoveApplication::class)->handle($karima, ApplicationStage::Withdrawn, RejectionReason::factory()->create()->id);

        Livewire::test(ApplicationPipeline::class, ['postingId' => $this->posting->id, 'tab' => 'all'])
            ->set('selected', [(string) $this->rahim->id, (string) $karima->id])
            ->call('startMove')
            ->assertDispatched('toast', variant: 'error')
            ->assertSet('movingIds', []);
    }

    public function test_changing_tab_clears_the_ticks(): void
    {
        Livewire::test(ApplicationPipeline::class, ['postingId' => $this->posting->id])
            ->set('selected', [(string) $this->rahim->id])
            ->set('tab', 'all')
            ->assertSet('selected', []);
    }

    public function test_an_application_of_another_posting_or_unit_gives_404(): void
    {
        $other = Application::factory()->create();

        Livewire::test(ApplicationPipeline::class, ['postingId' => $this->posting->id])
            ->set('selected', [(string) $other->id])   // not of this posting: ignored
            ->call('startMove')
            ->assertSet('movingIds', []);

        // HR limited to another entity cannot open this posting's pipeline at all.
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.recruitment.manage']);
        Role::where('name', 'like', 'Test role%')->latest('id')->firstOrFail()->update(['entity_id' => Entity::factory()->create()->id]);
        Livewire::test(ApplicationPipeline::class, ['postingId' => $this->posting->id])->assertNotFound();
    }

    public function test_the_pipeline_keeps_working_after_the_posting_closes(): void
    {
        $this->posting->close();

        Livewire::test(ApplicationPipeline::class, ['postingId' => $this->posting->id])
            ->set('selected', [(string) $this->rahim->id])
            ->call('startMove')
            ->set('to', 'interview')
            ->call('move')
            ->assertHasNoErrors();

        $this->assertSame(ApplicationStage::Interview, $this->rahim->fresh()->stage);
    }
}
