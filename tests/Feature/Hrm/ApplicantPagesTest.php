<?php

namespace Tests\Feature\Hrm;

use App\Core\Support\TenantUploads;
use App\Models\Tenant;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Livewire\Recruitment\ApplicantEducations;
use App\Modules\Hrm\Livewire\Recruitment\ApplicantForm;
use App\Modules\Hrm\Livewire\Recruitment\ApplicantProfile;
use App\Modules\Hrm\Livewire\Recruitment\ApplicantWorkHistories;
use App\Modules\Hrm\Livewire\Recruitment\JobPostingDetails;
use App\Modules\Hrm\Models\Applicant;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\JobPosting;
use App\Modules\Hrm\Models\ManpowerRequest;
use App\Modules\Hrm\Models\OrgUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 6.4: HRM → Applicants (list, New / Edit with duplicate check and CV, profile with education,
 * work history and applications) and the posting page's Applicants card. [REC-03]
 */
class ApplicantPagesTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private JobPosting $posting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->actingAsTenantUser()->tenant;   // Administrator
        $this->posting = JobPosting::factory()->open()->create(['title' => 'Sales Officer']);
    }

    private function newApplicantForm(?int $postingId = null): Testable
    {
        return Livewire::test(ApplicantForm::class)
            ->dispatch('create-applicant', postingId: $postingId)
            ->set('form.first_name', 'Rahim')
            ->set('form.last_name', 'Uddin')
            ->set('form.email', 'rahim@mail.com')
            ->set('form.phone', '01711-000000');
    }

    public function test_pages_open_from_the_menu_and_need_the_permission(): void
    {
        $applicant = Applicant::factory()->create(['first_name' => 'Karima']);

        $this->get('/dashboard')->assertSee(route('hrm.recruitment.applicants'));
        $this->get('/hrm/recruitment/applicants')->assertOk()->assertSee('Karima');
        $this->get(route('hrm.recruitment.applicants.show', $applicant))->assertOk()->assertSee('Karima');

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.recruitment.request']);
        $this->get('/hrm/recruitment/applicants')->assertForbidden();
        $this->get(route('hrm.recruitment.applicants.show', $applicant))->assertForbidden();
    }

    public function test_a_new_applicant_with_cv_applies_to_the_chosen_posting(): void
    {
        $this->newApplicantForm($this->posting->id)
            ->assertSet('postingId', $this->posting->id)
            ->set('cv', UploadedFile::fake()->create('Rahim CV.pdf', 300, 'application/pdf'))
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('hrm.recruitment.applicants.show', Applicant::sole()->id));

        $rahim = Applicant::sole();
        $this->assertSame('01711000000', $rahim->phone);
        $this->assertSame('Rahim CV.pdf', $rahim->cv_name);
        $this->assertTrue(app(TenantUploads::class)->exists((string) $rahim->cv_path));
        $this->assertSame([$this->posting->id], $rahim->applications->pluck('job_posting_id')->all());

        $this->get(route('hrm.recruitment.applicants.cv', $rahim))->assertOk()->assertDownload('Rahim CV.pdf');
    }

    public function test_email_or_phone_is_needed_and_files_are_checked(): void
    {
        Livewire::test(ApplicantForm::class)
            ->dispatch('create-applicant')
            ->set('form.first_name', 'Rahim')
            ->set('cv', UploadedFile::fake()->create('cv.exe', 10))
            ->call('save')
            ->assertHasErrors(['form.email', 'form.phone', 'cv']);

        $this->assertSame(0, Applicant::count());
    }

    public function test_a_duplicate_is_shown_refused_and_can_be_used_instead(): void
    {
        $existing = Applicant::factory()->create(['first_name' => 'Rahim', 'phone' => '+8801711000000']);

        $form = $this->newApplicantForm($this->posting->id)
            ->set('form.email', 'new-address@mail.com')
            ->assertSee('Already entered with this email or phone')
            ->assertSee('Use this person')
            ->call('save')
            ->assertHasErrors(['form.email']);
        $this->assertSame(1, Applicant::count());

        // "Use this person": the EXISTING applicant applies to the chosen posting.
        $form->call('useExisting', $existing->id)
            ->assertRedirect(route('hrm.recruitment.applicants.show', $existing->id));
        $this->assertSame([$existing->id], $this->posting->applications()->pluck('applicant_id')->all());

        // Someone who is NOT a duplicate of what was typed cannot be "used" (ids from the browser are not trusted).
        $stranger = Applicant::factory()->create();
        $this->newApplicantForm()->call('useExisting', $stranger->id)->assertNotFound();
    }

    public function test_a_closed_posting_cannot_be_chosen_and_nothing_is_half_saved(): void
    {
        $closed = JobPosting::factory()->create(['status' => 'closed']);

        $this->newApplicantForm()
            ->assertDontSee($closed->number())
            ->set('postingId', $closed->id)
            ->call('save')
            ->assertHasErrors(['postingId']);

        $this->assertSame(0, Applicant::count());   // no applicant without the application either
    }

    public function test_edit_replaces_the_cv_and_delete_needs_no_applications(): void
    {
        $uploads = app(TenantUploads::class);
        $this->newApplicantForm()->set('cv', UploadedFile::fake()->create('old.pdf', 10, 'application/pdf'))->call('save');
        $rahim = Applicant::sole();
        $oldPath = (string) $rahim->cv_path;

        Livewire::test(ApplicantForm::class)
            ->dispatch('edit-applicant', id: $rahim->id)
            ->assertSet('form.phone', '01711000000')
            ->set('form.current_employer', 'ACME Ltd')
            ->set('cv', UploadedFile::fake()->create('new.pdf', 10, 'application/pdf'))
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('applicant-saved');

        $rahim->refresh();
        $this->assertSame('ACME Ltd', $rahim->current_employer);
        $this->assertSame('new.pdf', $rahim->cv_name);
        $this->assertFalse($uploads->exists($oldPath));   // replaced: the old file is gone

        // With an application: not deleted.
        Application::factory()->for($rahim)->for($this->posting)->create();
        Livewire::test(ApplicantForm::class)->dispatch('edit-applicant', id: $rahim->id)->call('delete')->assertHasErrors(['form.first_name']);
        $this->assertSame(1, Applicant::count());

        // Without: deleted with the CV.
        $rahim->applications()->delete();
        $cv = (string) $rahim->fresh()->cv_path;
        Livewire::test(ApplicantForm::class)->dispatch('edit-applicant', id: $rahim->id)->call('delete')
            ->assertRedirect(route('hrm.recruitment.applicants'));
        $this->assertSame(0, Applicant::count());
        $this->assertFalse($uploads->exists($cv));
    }

    public function test_apply_to_posting_from_the_profile(): void
    {
        $rahim = Applicant::factory()->create();
        $draft = JobPosting::factory()->create();

        Livewire::test(ApplicantProfile::class, ['applicantId' => $rahim->id])
            ->assertDontSee($draft->number())
            ->call('openApply')
            ->set('postingId', $this->posting->id)
            ->call('apply')
            ->assertHasNoErrors()
            ->assertSee('Sales Officer')
            ->assertSee('Applied')
            // Applied now: the posting is no longer offered, and a second try is refused.
            ->set('postingId', $this->posting->id)
            ->call('apply')
            ->assertHasErrors(['postingId']);

        $this->assertSame(1, Application::count());
    }

    public function test_applications_to_postings_in_other_units_are_counted_not_shown(): void
    {
        $sales = OrgUnit::factory()->create();
        $mine = JobPosting::factory()->open()->for(ManpowerRequest::factory()->approved()->create(['org_unit_id' => $sales->id]))->create(['title' => 'Sales Officer']);
        $rahim = Applicant::factory()->create();
        Application::factory()->for($rahim)->for($mine)->create();
        Application::factory()->for($rahim)->for(JobPosting::factory()->open()->create(['title' => 'Accountant']))->create();

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.recruitment.manage']);
        Role::where('name', 'like', 'Test role%')->latest('id')->firstOrFail()->update(['org_unit_id' => $sales->id]);

        Livewire::test(ApplicantProfile::class, ['applicantId' => $rahim->id])
            ->assertSee('Sales Officer')
            ->assertDontSee('Accountant')
            ->assertSee('+1 application in org units you cannot see');
    }

    public function test_education_and_work_history_rows(): void
    {
        $rahim = Applicant::factory()->create();
        $other = Applicant::factory()->create();
        $theirRow = $other->educations()->create(['degree' => 'HSC']);

        Livewire::test(ApplicantEducations::class, ['applicantId' => $rahim->id])
            ->call('add')
            ->set('form.degree', 'BBA')
            ->set('form.score', '3.9')
            ->set('form.out_of', '4')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('3.9 / 4')
            ->call('add')
            ->set('form.degree', 'MBA')
            ->set('form.score', '5')
            ->set('form.out_of', '4')
            ->call('save')
            ->assertHasErrors(['form.score'])
            ->call('edit', $theirRow->id)   // another applicant's row
            ->assertNotFound();

        Livewire::test(ApplicantWorkHistories::class, ['applicantId' => $rahim->id])
            ->call('add')
            ->set('form.organization', 'ACME Ltd')
            ->set('form.from_date', '2022-01-01')
            ->set('form.to_date', '2021-01-01')
            ->call('save')
            ->assertHasErrors(['form.to_date'])
            ->set('form.to_date', '')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('ACME Ltd');

        $this->assertSame(['BBA'], $rahim->educations()->pluck('degree')->all());
        $this->assertSame(1, $rahim->workHistories()->count());
    }

    public function test_the_posting_page_lists_its_applicants(): void
    {
        $rahim = Applicant::factory()->create(['first_name' => 'Rahim']);
        Application::factory()->for($rahim)->for($this->posting)->create();

        Livewire::test(JobPostingDetails::class, ['postingId' => $this->posting->id])
            ->assertSee('Rahim')
            ->assertSee('1 application')
            ->assertSee('Add applicant');

        $this->posting->close();
        Livewire::test(JobPostingDetails::class, ['postingId' => $this->posting->id])->assertDontSee('Add applicant');
    }
}
