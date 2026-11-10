<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Actions\ApplyToPosting;
use App\Modules\Hrm\Actions\MoveApplication;
use App\Modules\Hrm\Enums\ApplicationStage;
use App\Modules\Hrm\Enums\JobPostingStatus;
use App\Modules\Hrm\Livewire\Recruitment\Reports\OpenPositionsReport;
use App\Modules\Hrm\Livewire\Recruitment\Reports\PipelineFunnelReport;
use App\Modules\Hrm\Livewire\Recruitment\Reports\TimeToHireReport;
use App\Modules\Hrm\Models\Applicant;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\JobPosting;
use App\Modules\Hrm\Models\ManpowerRequest;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Models\RejectionReason;
use App\Modules\Hrm\Services\RecruitmentStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 6.9: recruitment reports. [REC-01..07]
 */
class RecruitmentReportsTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private OrgUnit $sales;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->actingAsTenantUser()->tenant;   // Administrator
        $this->sales = OrgUnit::factory()->create(['name' => 'Sales']);
    }

    /** An approved request for $headcount people, $hired of them hired. */
    private function request(string $designation, int $headcount, int $hired = 0, array $attributes = []): ManpowerRequest
    {
        $request = ManpowerRequest::factory()->approved()->create([
            'org_unit_id' => $this->sales->id,
            'designation_id' => Designation::factory()->create(['name' => $designation])->id,
            'headcount' => $headcount,
            ...$attributes,
        ]);
        $request->forceFill(['hired_count' => $hired])->save();

        return $request;
    }

    public function test_the_page_opens_from_the_menu_and_needs_the_permission(): void
    {
        $this->get('/dashboard')->assertSee(route('hrm.recruitment'));
        $this->get(route('hrm.recruitment.reports.open-positions'))->assertOk()->assertSee('Open positions');

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.recruitment.request']);
        $this->get(route('hrm.recruitment.reports.open-positions'))->assertForbidden();
    }

    public function test_open_positions_lists_approved_requests_with_people_still_to_hire(): void
    {
        $open = $this->request('Sales Officer', headcount: 3, hired: 1);
        JobPosting::factory()->for($open)->open()->create(['openings' => 1]);
        JobPosting::factory()->for($open)->create(['openings' => 1, 'status' => JobPostingStatus::Closed]);   // not counted

        $this->request('Accountant', headcount: 1, hired: 1);                       // filled
        ManpowerRequest::factory()->create(['designation_id' => Designation::factory()->create(['name' => 'Driver'])->id]);   // not approved

        Livewire::test(OpenPositionsReport::class)
            ->assertSee('Sales Officer')
            ->assertDontSee('Accountant')
            ->assertDontSee('Driver')
            ->assertSeeInOrder([$open->number(), 'Sales Officer', 'Sales', '3', '1', '2', '1']);   // wanted 3, hired 1, open 2, advertised 1
    }

    public function test_filters_overdue_and_advertised(): void
    {
        $late = $this->request('Late Role', 1, attributes: ['needed_by' => today()->subDay()->toDateString()]);
        $soon = $this->request('Soon Role', 2, attributes: ['needed_by' => today()->addDays(10)->toDateString()]);
        JobPosting::factory()->for($soon)->open()->create(['openings' => 2]);

        Livewire::test(OpenPositionsReport::class)
            ->assertSee('Overdue')
            ->set('filters.timing', 'overdue')
            ->assertSee('Late Role')->assertDontSee('Soon Role')
            ->set('filters.timing', '')
            ->set('filters.advertised', 'no')
            ->assertSee('Late Role')->assertDontSee('Soon Role')
            ->set('filters.advertised', 'all')
            ->assertSee('Soon Role')->assertDontSee('Late Role');

        $this->assertNotNull($late);
    }

    public function test_only_the_users_org_units(): void
    {
        $this->request('Sales Officer', 1);
        $finance = OrgUnit::factory()->create();
        $this->request('Accountant', 1, attributes: ['org_unit_id' => $finance->id]);

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.recruitment.manage']);
        Role::where('name', 'like', 'Test role%')->latest('id')->firstOrFail()->update(['org_unit_id' => $this->sales->id]);

        Livewire::test(OpenPositionsReport::class)->assertSee('Sales Officer')->assertDontSee('Accountant');
    }

    public function test_the_worked_out_columns_can_be_sorted(): void
    {
        $this->request('Two Open', 2);
        $this->request('Five Open', 5);

        Livewire::test(OpenPositionsReport::class)
            ->call('sort', 'open_count')->assertSeeInOrder(['Two Open', 'Five Open'])
            ->call('sort', 'open_count')->assertSeeInOrder(['Five Open', 'Two Open'])   // second click: descending
            ->call('sort', 'advertised')->assertOk();
    }

    public function test_export_to_excel(): void
    {
        $this->request('Sales Officer', 2);

        Livewire::test(OpenPositionsReport::class)->call('export')->assertFileDownloaded('open-positions-report-'.now()->format('Y-m-d').'.xlsx');
    }

    // ---- 6.9b: pipeline funnel ---------------------------------------------------------------

    /** An applicant on $posting moved through $stages (by hand or by the system), as the real actions do. */
    private function through(JobPosting $posting, array $stages): Application
    {
        $application = app(ApplyToPosting::class)->handle(Applicant::factory()->create(), $posting);
        foreach ($stages as $stage) {
            $reason = $stage->needsReason() ? RejectionReason::factory()->create()->id : null;
            app(MoveApplication::class)->bySystem($application->fresh(), $stage, $reason);
        }

        return $application->fresh();
    }

    public function test_the_funnel_counts_everyone_who_ever_reached_a_step(): void
    {
        $posting = JobPosting::factory()->for($this->request('Sales Officer', 2))->open()->create(['title' => 'Sales Officer']);

        $this->through($posting, []);                                                                        // applied
        $this->through($posting, [ApplicationStage::Shortlisted, ApplicationStage::Rejected]);               // reached shortlisted
        $this->through($posting, [ApplicationStage::Interview, ApplicationStage::Rejected]);                 // skipped shortlisted
        $this->through($posting, [ApplicationStage::Interview, ApplicationStage::Offered, ApplicationStage::Withdrawn]);
        $this->through($posting, [ApplicationStage::Shortlisted, ApplicationStage::Interview, ApplicationStage::Offered, ApplicationStage::Hired]);

        $row = app(RecruitmentStats::class)->funnel(collect([$posting]))->sole();

        // applied 5, shortlisted 4 (skipping counts), interview 3, offered 2, hired 1
        $this->assertSame([1 => 5, 2 => 4, 3 => 3, 4 => 2, 5 => 1], $row['reached']);
        $this->assertSame(2, $row['rejected']);
        $this->assertSame(1, $row['withdrawn']);

        Livewire::test(PipelineFunnelReport::class)
            ->assertSee('Sales Officer')
            ->assertSeeInOrder(['5', '4', '80%', '3', '60%', '2', '40%', '1', '20%']);
    }

    public function test_the_funnel_filters_and_exports(): void
    {
        JobPosting::factory()->for($this->request('Open One', 1))->open()->create(['title' => 'Open One']);
        JobPosting::factory()->for($this->request('Closed One', 1))->create(['title' => 'Closed One', 'status' => JobPostingStatus::Closed]);

        Livewire::test(PipelineFunnelReport::class)
            ->set('status', 'closed')
            ->assertSee('Closed One')->assertDontSee('Open One')
            ->call('export')
            ->assertFileDownloaded('recruitment-funnel-'.now()->format('Y-m-d').'.xlsx');

        $this->get(route('hrm.recruitment.reports.funnel'))->assertOk();
    }

    // ---- 6.9c: time to hire ------------------------------------------------------------------

    public function test_time_to_hire_and_time_to_fill_in_days(): void
    {
        $this->travelTo(today()->setDate(2026, 10, 1));
        $request = $this->request('Sales Officer', 2);   // approved 1 Oct
        $posting = JobPosting::factory()->for($request)->open()->create(['close_date' => null]);

        $this->travelTo(today()->setDate(2026, 10, 11));
        $first = $this->through($posting, [ApplicationStage::Interview, ApplicationStage::Offered]);   // applied 11 Oct
        $this->travelTo(today()->setDate(2026, 10, 21));
        $second = $this->through($posting, [ApplicationStage::Offered]);                               // applied 21 Oct

        $this->travelTo(today()->setDate(2026, 10, 31));
        app(MoveApplication::class)->bySystem($first, ApplicationStage::Hired);    // 20 days to hire, 30 to fill
        $this->travelTo(today()->setDate(2026, 11, 30));
        app(MoveApplication::class)->bySystem($second, ApplicationStage::Hired);   // 40 days to hire, 60 to fill

        $stats = app(RecruitmentStats::class);
        $hires = $stats->hires(auth()->user());

        $this->assertSame([40, 20], $hires->pluck('days_to_hire')->all());   // newest first
        $this->assertSame([60, 30], $hires->pluck('days_to_fill')->all());
        $this->assertSame(['count' => 2, 'average' => 30.0, 'median' => 30.0], $stats->summary($hires->pluck('days_to_hire')));

        Livewire::test(TimeToHireReport::class)
            ->set('months', '')
            ->assertSee('30 days')    // average time to hire
            ->assertSee('45 days')    // average time to fill
            ->assertSee('Sales');

        // Only hires on or after the date: from 10 Nov only the second one (30 Nov), not the first (31 Oct).
        $this->assertSame([$second->id], $stats->hires(auth()->user(), today()->subDays(20))->pluck('application.id')->all());
    }

    public function test_time_to_hire_page_export_and_empty_state(): void
    {
        $this->get(route('hrm.recruitment.reports.time-to-hire'))->assertOk()->assertSee('No hires in this period');

        Livewire::test(TimeToHireReport::class)->call('export')->assertFileDownloaded('time-to-hire-'.now()->format('Y-m-d').'.xlsx');
    }

    public function test_hires_in_other_org_units_are_left_out(): void
    {
        $finance = OrgUnit::factory()->create();
        $posting = JobPosting::factory()->for($this->request('Accountant', 1, attributes: ['org_unit_id' => $finance->id]))->open()->create();
        $this->through($posting, [ApplicationStage::Offered, ApplicationStage::Hired]);

        $user = $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.recruitment.manage']);
        Role::where('name', 'like', 'Test role%')->latest('id')->firstOrFail()->update(['org_unit_id' => $this->sales->id]);

        $this->assertTrue(app(RecruitmentStats::class)->hires($user)->isEmpty());
        Livewire::test(PipelineFunnelReport::class)->assertDontSee($posting->number());
    }
}
