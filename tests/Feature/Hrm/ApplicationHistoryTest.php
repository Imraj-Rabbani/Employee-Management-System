<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Actions\ApplyToPosting;
use App\Modules\Hrm\Actions\MoveApplication;
use App\Modules\Hrm\Enums\ApplicationStage;
use App\Modules\Hrm\Livewire\Recruitment\ApplicantProfile;
use App\Modules\Hrm\Livewire\Recruitment\ApplicationHistory;
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
 * Task 6.5: an application's history timeline (modal on the posting and applicant pages). [REC-04]
 */
class ApplicationHistoryTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $hr;

    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hr = $this->actingAsTenantUser(['name' => 'Nadia HR']);
        $this->tenant = $this->hr->tenant;

        $posting = JobPosting::factory()->open()->create(['title' => 'Sales Officer']);
        $this->application = app(ApplyToPosting::class)->handle(Applicant::factory()->create(['first_name' => 'Rahim']), $posting, $this->hr->id);

        $move = app(MoveApplication::class);
        $move->handle($this->application, ApplicationStage::Shortlisted, note: 'Strong CV', byUserId: $this->hr->id);
        $move->handle($this->application, ApplicationStage::Rejected, RejectionReason::factory()->create(['name' => 'Salary too high'])->id, byUserId: $this->hr->id);
    }

    public function test_the_timeline_shows_every_move_in_order_with_who_note_and_reason(): void
    {
        Livewire::test(ApplicationHistory::class)
            ->dispatch('show-application-history', id: $this->application->id)
            ->assertSee('Rahim')
            ->assertSee('Sales Officer')
            ->assertSeeInOrder(['Applied', 'Shortlisted', 'Strong CV', 'Rejected', 'Salary too high'])
            ->assertSeeHtml('Applied →')       // "from →" on every move after the first
            ->assertSeeHtml('Shortlisted →')
            ->assertSee('Nadia HR');
    }

    public function test_both_pages_have_the_history_modal_and_a_button_for_it(): void
    {
        $this->get(route('hrm.recruitment.postings.show', $this->application->job_posting_id))->assertSeeLivewire(ApplicationHistory::class);
        $this->get(route('hrm.recruitment.applicants.show', $this->application->applicant_id))->assertSeeLivewire(ApplicationHistory::class);

        // The buttons that open it: the pipeline row and the applicant profile's stage badge.
        Livewire::test(ApplicationPipeline::class, ['postingId' => $this->application->job_posting_id, 'tab' => 'all'])
            ->assertSeeHtml('show-application-history');
        Livewire::test(ApplicantProfile::class, ['applicantId' => $this->application->applicant_id])
            ->assertSeeHtml('show-application-history');
    }

    public function test_an_application_outside_your_org_units_gives_404(): void
    {
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.recruitment.manage']);
        Role::where('name', 'like', 'Test role%')->latest('id')->firstOrFail()->update(['org_unit_id' => OrgUnit::factory()->create()->id]);

        Livewire::test(ApplicationHistory::class)
            ->dispatch('show-application-history', id: $this->application->id)
            ->assertNotFound();
    }
}
