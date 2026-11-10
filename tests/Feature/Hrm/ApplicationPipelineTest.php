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
use App\Modules\Hrm\Models\JobPosting;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Models\RejectionReason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 6.5: the pipeline on a job posting's page (tabs per stage, Move with note / reason). [REC-04]
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
            ->call('startMove', $this->rahim->id)
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
            ->call('startMove', $this->rahim->id)
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
            ->call('startMove', $this->rahim->id)
            ->set('to', 'shortlisted')   // backwards
            ->call('move')
            ->assertHasErrors(['to'])
            ->set('to', 'hired')         // only by the hire (6.8)
            ->call('move')
            ->assertHasErrors(['to']);

        $this->assertSame(ApplicationStage::Interview, $this->rahim->fresh()->stage);
    }

    public function test_closed_applications_have_no_move_button(): void
    {
        app(MoveApplication::class)->handle($this->rahim, ApplicationStage::Withdrawn, RejectionReason::factory()->create()->id);

        Livewire::test(ApplicationPipeline::class, ['postingId' => $this->posting->id, 'tab' => 'withdrawn'])
            ->assertSee('Rahim')
            ->assertDontSeeHtml('wire:click="startMove('.$this->rahim->id.')"');
    }

    public function test_an_application_of_another_posting_or_unit_gives_404(): void
    {
        $other = Application::factory()->create();

        Livewire::test(ApplicationPipeline::class, ['postingId' => $this->posting->id])
            ->call('startMove', $other->id)
            ->assertNotFound();

        // HR limited to another org unit cannot open this posting's pipeline at all.
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.recruitment.manage']);
        Role::where('name', 'like', 'Test role%')->latest('id')->firstOrFail()->update(['org_unit_id' => OrgUnit::factory()->create()->id]);
        Livewire::test(ApplicationPipeline::class, ['postingId' => $this->posting->id])->assertNotFound();
    }

    public function test_the_pipeline_keeps_working_after_the_posting_closes(): void
    {
        $this->posting->close();

        Livewire::test(ApplicationPipeline::class, ['postingId' => $this->posting->id])
            ->call('startMove', $this->rahim->id)
            ->set('to', 'interview')
            ->call('move')
            ->assertHasNoErrors();

        $this->assertSame(ApplicationStage::Interview, $this->rahim->fresh()->stage);
    }
}
