<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Enums\JobPostingStatus;
use App\Modules\Hrm\Models\JobPosting;
use App\Modules\Hrm\Models\ManpowerRequest;
use App\Modules\Hrm\Models\OrgUnit;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 6.3: job postings made from approved manpower requests. [REC-02]
 */
class JobPostingTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->actingAsTenantUser()->tenant;   // Administrator
    }

    public function test_a_new_posting_is_a_draft_of_an_approved_request(): void
    {
        $posting = JobPosting::factory()->create()->fresh();

        $this->assertSame(JobPostingStatus::Draft, $posting->status);
        $this->assertNotNull($posting->manpowerRequest->approved_at);
        $this->assertSame([$posting->id], $posting->manpowerRequest->jobPostings->pluck('id')->all());
        $this->assertSame('JOB-'.str_pad((string) $posting->id, 4, '0', STR_PAD_LEFT), $posting->number());
    }

    public function test_accepting_applications_needs_open_status_and_today_inside_the_dates(): void
    {
        $posting = JobPosting::factory()->open()->create(['open_date' => '2026-11-01', 'close_date' => '2026-11-30']);

        $this->assertTrue($posting->isAcceptingApplications(today()->setDate(2026, 11, 1)));    // first day counts
        $this->assertTrue($posting->isAcceptingApplications(today()->setDate(2026, 11, 30)));   // last day counts
        $this->assertFalse($posting->isAcceptingApplications(today()->setDate(2026, 10, 31)));
        $this->assertFalse($posting->isAcceptingApplications(today()->setDate(2026, 12, 1)));

        $posting->update(['close_date' => null]);   // no close date: until closed by hand
        $this->assertTrue($posting->isAcceptingApplications(today()->setDate(2027, 6, 1)));

        $posting->update(['status' => JobPostingStatus::Closed]);
        $this->assertFalse($posting->isAcceptingApplications(today()->setDate(2026, 11, 15)));
    }

    public function test_openings_to_advertise_counts_only_draft_and_open_postings(): void
    {
        $request = ManpowerRequest::factory()->approved()->create(['headcount' => 5]);
        $this->assertSame(5, $request->openingsToAdvertise());

        $first = JobPosting::factory()->for($request)->open()->create(['openings' => 3]);
        $this->assertSame(2, $request->openingsToAdvertise());
        $this->assertSame(5, $request->openingsToAdvertise(exceptPostingId: $first->id));   // editing $first

        // Closed without hiring: those 3 can be advertised again.
        $first->update(['status' => JobPostingStatus::Closed]);
        $this->assertSame(5, $request->openingsToAdvertise());

        // People hired (6.8) are no longer open at all.
        $request->forceFill(['hired_count' => 4])->save();
        $this->assertSame(1, $request->fresh()->openingsToAdvertise());
    }

    public function test_visible_to_follows_the_requests_org_unit(): void
    {
        $sales = OrgUnit::factory()->create();
        $finance = OrgUnit::factory()->create();
        $mine = JobPosting::factory()->for(ManpowerRequest::factory()->approved()->create(['org_unit_id' => $sales->id]))->create();
        JobPosting::factory()->for(ManpowerRequest::factory()->approved()->create(['org_unit_id' => $finance->id]))->create();

        $user = $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.recruitment.request']);
        Role::where('name', 'like', 'Test role%')->latest('id')->firstOrFail()->update(['org_unit_id' => $sales->id]);

        $this->assertSame([$mine->id], JobPosting::visibleTo($user)->pluck('id')->all());
    }

    public function test_a_request_with_postings_cannot_be_deleted(): void
    {
        $posting = JobPosting::factory()->create();

        $this->expectException(QueryException::class);
        $posting->manpowerRequest->delete();
    }

    // ---- 6.3c: open / close ---------------------------------------------------------------

    public function test_a_draft_opens_and_an_open_posting_closes(): void
    {
        $posting = JobPosting::factory()->create();

        $this->assertNull($posting->openProblem());
        $posting->open();
        $this->assertSame(JobPostingStatus::Open, $posting->fresh()->status);
        $this->assertSame('This posting is already open.', $posting->openProblem());

        $posting->close();
        $this->assertSame(JobPostingStatus::Closed, $posting->fresh()->status);

        $this->expectException(\LogicException::class);
        $posting->close();   // already closed
    }

    public function test_a_posting_past_its_close_date_cannot_be_opened(): void
    {
        $posting = JobPosting::factory()->create(['open_date' => today()->subMonth(), 'close_date' => today()->subDay()]);

        $this->assertStringContainsString('close date has passed', (string) $posting->openProblem());

        $this->expectException(\LogicException::class);
        $posting->open();
    }

    public function test_reopening_must_fit_the_openings_left(): void
    {
        $request = ManpowerRequest::factory()->approved()->create(['headcount' => 3]);
        $closed = JobPosting::factory()->for($request)->create(['openings' => 3, 'status' => JobPostingStatus::Closed]);
        JobPosting::factory()->for($request)->open()->create(['openings' => 2]);   // advertised again meanwhile

        $this->assertStringContainsString('only 1 opening(s) left', (string) $closed->openProblem());

        $closed->update(['openings' => 1]);
        $this->assertNull($closed->fresh()->openProblem());
    }

    public function test_the_application_window_line(): void
    {
        $posting = JobPosting::factory()->open()->create(['open_date' => today()->addDays(3), 'close_date' => null]);
        $this->assertStringStartsWith('Opens on', $posting->applicationWindow());

        $posting->update(['open_date' => today()]);
        $this->assertSame('Taking applications', $posting->applicationWindow());

        $posting->update(['open_date' => today()->subMonth(), 'close_date' => today()->subDay()]);
        $this->assertStringStartsWith('Close date passed', $posting->applicationWindow());

        $posting->update(['status' => JobPostingStatus::Closed]);
        $this->assertSame('Closed', $posting->applicationWindow());
    }
}
