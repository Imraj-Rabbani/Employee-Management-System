<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Actions\ApplyToPosting;
use App\Modules\Hrm\Actions\MakeOffer;
use App\Modules\Hrm\Actions\MoveApplication;
use App\Modules\Hrm\Enums\ApplicationStage;
use App\Modules\Hrm\Livewire\Recruitment\ApplicantProfile;
use App\Modules\Hrm\Livewire\Recruitment\HireForm;
use App\Modules\Hrm\Livewire\Recruitment\OfferDetails;
use App\Modules\Hrm\Models\Applicant;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\JobOffer;
use App\Modules\Hrm\Models\JobPosting;
use App\Modules\Hrm\Models\ManpowerRequest;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 6.8: the Hire button and form on an accepted offer, and the links to the new employee. [REC-07]
 */
class HirePagesTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private OrgUnit $sales;

    private Position $seat;

    private JobOffer $offer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->actingAsTenantUser()->tenant;   // Administrator

        $this->sales = OrgUnit::factory()->create(['name' => 'Sales']);
        $this->seat = Position::factory()->create(['org_unit_id' => $this->sales->id, 'employee_id' => null, 'name' => 'Sales Officer 1']);
        $request = ManpowerRequest::factory()->approved()->create(['org_unit_id' => $this->sales->id, 'position_id' => $this->seat->id]);

        $application = app(ApplyToPosting::class)->handle(Applicant::factory()->create(['first_name' => 'Rahim']), JobPosting::factory()->for($request)->open()->create());
        app(MoveApplication::class)->bySystem($application, ApplicationStage::Offered);
        $this->offer = JobOffer::factory()->for($application->fresh())->approved()->create(['designation_id' => $request->designation_id]);
    }

    private function accept(): void
    {
        app(MakeOffer::class)->accept($this->offer, today());
    }

    public function test_hire_appears_only_on_an_accepted_offer(): void
    {
        Livewire::test(OfferDetails::class, ['offerId' => $this->offer->id])->assertDontSeeHtml("hire', {");   // approved, not accepted

        $this->accept();
        Livewire::test(OfferDetails::class, ['offerId' => $this->offer->id])
            ->assertSeeHtml("hire', { offerId: {$this->offer->id} }");
    }

    public function test_the_form_is_prefilled_and_saving_opens_the_new_profile(): void
    {
        $this->accept();

        Livewire::test(HireForm::class)
            ->dispatch('hire', offerId: $this->offer->id)
            ->assertSee('Hire Rahim')
            ->assertSet('form.org_unit_id', $this->sales->id)
            ->assertSet('form.position_id', $this->seat->id)
            ->assertSee('Sales Officer 1')
            ->set('form.code', 'E-1001')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('hrm.employees.show', Employee::where('code', 'E-1001')->value('id')));

        $employee = Employee::where('code', 'E-1001')->sole();
        $this->assertSame($this->seat->id, $employee->position_id);

        // Afterwards: the offer page and the applicant profile link to the employee.
        Livewire::test(OfferDetails::class, ['offerId' => $this->offer->id])->assertSee('Employee E-1001')->assertDontSeeHtml("hire', {");
        Livewire::test(ApplicantProfile::class, ['applicantId' => $this->offer->application->applicant_id])->assertSee('Employee E-1001');
    }

    public function test_the_rules_show_under_the_right_field(): void
    {
        $this->accept();
        Employee::factory()->create(['code' => 'E-1001']);

        Livewire::test(HireForm::class)
            ->dispatch('hire', offerId: $this->offer->id)
            ->set('form.code', 'E-1001')               // taken
            ->call('save')
            ->assertHasErrors(['form.code'])
            ->set('form.org_unit_id', OrgUnit::factory()->create()->id)
            ->assertSet('form.position_id', '')       // cleared: the seat belonged to Sales
            ->set('form.position_id', $this->seat->id) // Sales' seat in another unit
            ->call('save')
            ->assertHasErrors(['form.position_id']);

        $this->assertSame(1, Employee::count());
    }

    public function test_hiring_needs_permission_to_add_employees(): void
    {
        $this->accept();
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.recruitment.manage']);   // no hrm.employee.create

        Livewire::test(OfferDetails::class, ['offerId' => $this->offer->id])->assertDontSeeHtml("hire', {");
        Livewire::test(HireForm::class)->dispatch('hire', offerId: $this->offer->id)->assertForbidden();
    }

    public function test_an_offer_outside_your_org_units_gives_404(): void
    {
        $this->accept();
        $mine = OrgUnit::factory()->childOf($this->sales)->create(['name' => 'Sales North']);

        // HR limited to Sales North only (a unit BELOW Sales, not Sales itself).
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.recruitment.manage', 'hrm.employee.create']);
        Role::where('name', 'like', 'Test role%')->latest('id')->firstOrFail()->update(['org_unit_id' => $mine->id]);

        // The posting's org unit (Sales) is not visible to this HR user, so the offer itself is 404 for them.
        Livewire::test(HireForm::class)->dispatch('hire', offerId: $this->offer->id)->assertNotFound();
    }
}
