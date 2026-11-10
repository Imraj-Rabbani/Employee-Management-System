<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Enums\JobPostingStatus;
use App\Modules\Hrm\Livewire\Recruitment\JobPostingDetails;
use App\Modules\Hrm\Livewire\Recruitment\JobPostingForm;
use App\Modules\Hrm\Livewire\Recruitment\JobPostingTable;
use App\Modules\Hrm\Livewire\Recruitment\ManpowerRequestDetails;
use App\Modules\Hrm\Livewire\Recruitment\ManpowerRequestForm;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\JobPosting;
use App\Modules\Hrm\Models\ManpowerRequest;
use App\Modules\Hrm\Models\OrgUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 6.3: HRM → Job Postings (list + New from an approved request / Edit / Delete). [REC-02]
 */
class JobPostingPagesTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private OrgUnit $sales;

    private ManpowerRequest $approved;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->actingAsTenantUser()->tenant;   // Administrator

        $this->sales = OrgUnit::factory()->create(['name' => 'Sales']);
        $this->approved = ManpowerRequest::factory()->approved()->create([
            'org_unit_id' => $this->sales->id,
            'designation_id' => Designation::factory()->create(['name' => 'Sales Officer'])->id,
            'headcount' => 3,
        ]);
    }

    /** HR: may manage postings, nothing else. Optionally limited to one org unit. */
    private function hrUser(?OrgUnit $limitedTo = null): User
    {
        $user = $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.recruitment.manage']);
        if ($limitedTo) {
            Role::where('name', 'like', 'Test role%')->latest('id')->firstOrFail()->update(['org_unit_id' => $limitedTo->id]);
        }

        return $user;
    }

    public function test_the_page_opens_from_the_menu_and_needs_the_permission(): void
    {
        $this->get('/dashboard')->assertSee(route('hrm.recruitment'));
        $this->get('/hrm/recruitment/postings')->assertOk();

        $this->hrUser();
        $this->get('/hrm/recruitment/postings')->assertOk();

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.recruitment.request']);
        $this->get('/hrm/recruitment/postings')->assertForbidden();
    }

    public function test_picking_an_approved_request_prefills_title_and_openings(): void
    {
        ManpowerRequest::factory()->create(['org_unit_id' => $this->sales->id]);   // a draft: not offered

        Livewire::test(JobPostingForm::class)
            ->dispatch('create-job-posting')
            ->assertSee($this->approved->number())
            ->set('form.manpower_request_id', $this->approved->id)
            ->assertSet('form.title', 'Sales Officer')
            ->assertSet('form.openings', 3)
            ->set('form.description', '')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('job-posting-saved');

        $posting = JobPosting::sole();
        $this->assertSame(JobPostingStatus::Draft, $posting->status);
        $this->assertSame($this->approved->id, $posting->manpower_request_id);
        $this->assertNull($posting->description);
        $this->assertSame(auth()->id(), $posting->created_by_user_id);
    }

    public function test_only_approved_visible_requests_with_openings_can_be_used(): void
    {
        $draft = ManpowerRequest::factory()->create(['org_unit_id' => $this->sales->id]);
        $full = ManpowerRequest::factory()->approved()->create(['headcount' => 1]);
        JobPosting::factory()->for($full)->create(['openings' => 1]);

        foreach ([$draft, $full] as $request) {
            Livewire::test(JobPostingForm::class)
                ->dispatch('create-job-posting')
                ->set('form.manpower_request_id', $request->id)
                ->set('form.title', 'X')
                ->call('save')
                ->assertHasErrors(['form.manpower_request_id']);
        }

        // HR limited to another unit does not get the Sales request.
        $this->hrUser(limitedTo: OrgUnit::factory()->create());
        Livewire::test(JobPostingForm::class)
            ->dispatch('create-job-posting')
            ->assertDontSee($this->approved->number())
            ->set('form.manpower_request_id', $this->approved->id)
            ->set('form.title', 'X')
            ->call('save')
            ->assertHasErrors(['form.manpower_request_id']);

        $this->assertSame(1, JobPosting::count());
    }

    public function test_openings_and_dates_are_checked(): void
    {
        JobPosting::factory()->for($this->approved)->create(['openings' => 2]);   // 1 left

        Livewire::test(JobPostingForm::class)
            ->dispatch('create-job-posting', requestId: $this->approved->id)
            ->assertSet('form.openings', 1)
            ->set('form.openings', 2)
            ->set('form.close_date', today()->subDay()->toDateString())   // before open date (today)
            ->call('save')
            ->assertHasErrors(['form.openings', 'form.close_date'])
            ->assertSee('only 1 opening(s) left');
    }

    public function test_editing_keeps_the_request_and_counts_its_own_openings(): void
    {
        $posting = JobPosting::factory()->for($this->approved)->open()->create(['openings' => 3]);
        $other = ManpowerRequest::factory()->approved()->create(['org_unit_id' => $this->sales->id]);

        Livewire::test(JobPostingForm::class)
            ->dispatch('edit-job-posting', id: $posting->id)
            ->set('form.manpower_request_id', $other->id)   // ignored: the request cannot be changed
            ->set('form.title', 'Senior Sales Officer')
            ->set('form.openings', 3)                        // its own 3 do not count against it
            ->call('save')
            ->assertHasNoErrors();

        $posting->refresh();
        $this->assertSame('Senior Sales Officer', $posting->title);
        $this->assertSame($this->approved->id, $posting->manpower_request_id);
    }

    public function test_closed_cannot_be_edited_and_only_a_draft_deleted(): void
    {
        $open = JobPosting::factory()->for($this->approved)->open()->create(['openings' => 1]);
        $closed = JobPosting::factory()->for($this->approved)->create(['openings' => 1, 'status' => JobPostingStatus::Closed]);
        $draft = JobPosting::factory()->for($this->approved)->create(['openings' => 1]);

        Livewire::test(JobPostingForm::class)->dispatch('edit-job-posting', id: $closed->id)
            ->set('form.title', 'Changed')->call('save')->assertHasErrors(['form.title']);
        Livewire::test(JobPostingForm::class)->dispatch('edit-job-posting', id: $open->id)
            ->call('delete')->assertHasErrors(['form.title']);
        Livewire::test(JobPostingForm::class)->dispatch('edit-job-posting', id: $draft->id)
            ->call('delete')->assertHasNoErrors()->assertRedirect(route('hrm.recruitment.postings'));

        $this->assertSame([$open->id, $closed->id], JobPosting::orderBy('id')->pluck('id')->all());
    }

    public function test_the_list_shows_only_visible_postings(): void
    {
        $mine = JobPosting::factory()->for($this->approved)->create(['title' => 'Sales Officer']);
        $theirs = JobPosting::factory()->create(['title' => 'Accountant']);   // another (new) org unit

        $this->hrUser(limitedTo: $this->sales);

        Livewire::test(JobPostingTable::class)->assertSee($mine->number())->assertDontSee($theirs->number());
        Livewire::test(JobPostingForm::class)->dispatch('edit-job-posting', id: $theirs->id)->assertNotFound();
    }

    public function test_hr_reads_an_approved_request_and_starts_a_posting_from_it(): void
    {
        $this->hrUser();

        // HR may open the request (view-requests) and sees "Create job posting", but not Edit / Send.
        $this->get(route('hrm.recruitment.requests.show', $this->approved->id))
            ->assertOk()
            ->assertSee(route('hrm.recruitment.postings', ['for' => $this->approved->id]), false);
        $this->get('/hrm/recruitment/requests')->assertForbidden();   // the list stays for requesters

        // ... and cannot change or send it, even by calling the actions directly.
        Livewire::test(ManpowerRequestDetails::class, ['requestId' => $this->approved->id])->call('submit')->assertForbidden();
        Livewire::test(ManpowerRequestForm::class)->dispatch('edit-manpower-request', id: $this->approved->id)->assertForbidden();

        // The link opens the posting form with the request already chosen.
        Livewire::test(JobPostingForm::class, ['for' => $this->approved->id])
            ->assertSet('form.manpower_request_id', $this->approved->id)
            ->assertSet('form.title', 'Sales Officer');
    }

    // ---- 6.3c: the posting's page ---------------------------------------------------------

    public function test_the_page_shows_the_posting_and_is_limited_to_visible_units(): void
    {
        $posting = JobPosting::factory()->for($this->approved)->create(['title' => 'Sales Officer', 'requirements' => 'BBA, 2 years']);
        $theirs = JobPosting::factory()->create();

        $this->get('/hrm/recruitment/postings')->assertSee(route('hrm.recruitment.postings.show', $posting->id));
        $this->get(route('hrm.recruitment.postings.show', $posting->id))
            ->assertOk()
            ->assertSee('Sales Officer')
            ->assertSee('BBA, 2 years')
            ->assertSee($this->approved->number())
            ->assertSee('Open for applications');

        $this->hrUser(limitedTo: $this->sales);
        $this->get(route('hrm.recruitment.postings.show', $theirs->id))->assertNotFound();
        Livewire::test(JobPostingDetails::class, ['postingId' => $theirs->id])->assertNotFound();

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.recruitment.request']);
        $this->get(route('hrm.recruitment.postings.show', $posting->id))->assertForbidden();
    }

    public function test_open_close_and_reopen_from_the_page(): void
    {
        $posting = JobPosting::factory()->for($this->approved)->create(['openings' => 3]);

        $page = Livewire::test(JobPostingDetails::class, ['postingId' => $posting->id])
            ->call('open')
            ->assertDispatched('toast', variant: 'success')
            ->assertSee('Taking applications');
        $this->assertSame(JobPostingStatus::Open, $posting->fresh()->status);

        $page->call('close')->assertDispatched('toast', variant: 'success')->assertSee('Reopen');
        $this->assertSame(JobPostingStatus::Closed, $posting->fresh()->status);

        // Meanwhile another posting took 2 of the 3 openings: reopening with 3 is refused, with the reason.
        JobPosting::factory()->for($this->approved)->open()->create(['openings' => 2]);
        $page->call('open')->assertDispatched('toast', variant: 'danger');
        $this->assertSame(JobPostingStatus::Closed, $posting->fresh()->status);
    }
}
