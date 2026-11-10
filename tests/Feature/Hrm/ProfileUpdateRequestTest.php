<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Hrm\Enums\MaritalStatus;
use App\Modules\Hrm\Livewire\Ess\ProfileRequests;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Models\ProfileUpdateRequest;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Enums\ApproverType;
use App\Modules\Workflow\Livewire\RespondForm;
use App\Modules\Workflow\Models\Approval;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowStep;
use App\Modules\Workflow\Services\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 5.8: profile update requests through the workflow, plus the employee-based approver types. [EMP-08]
 *
 * Head Office (head: Boss) › Finance (head: none). Rahim (Finance) reports to Salma. Everyone has a login
 * without any role (pure self service).
 */
class ProfileUpdateRequestTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private Employee $rahim;

    private Employee $salma;

    private Employee $boss;

    private OrgUnit $head;

    private OrgUnit $finance;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->tenant = $this->actingAsTenantUser()->tenant;

        $this->head = OrgUnit::factory()->create(['name' => 'Head Office']);
        $this->finance = OrgUnit::factory()->childOf($this->head)->create(['name' => 'Finance']);

        $this->boss = $this->employeeWithLogin(['first_name' => 'Boss', 'org_unit_id' => $this->head->id]);
        $this->head->update(['head_employee_id' => $this->boss->id]);
        $this->salma = $this->employeeWithLogin(['first_name' => 'Salma', 'org_unit_id' => $this->finance->id]);
        $this->rahim = $this->employeeWithLogin([
            'first_name' => 'Rahim', 'last_name' => 'Uddin', 'org_unit_id' => $this->finance->id,
            'supervisor_id' => $this->salma->id, 'mobile' => '01711000000',
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function employeeWithLogin(array $attributes): Employee
    {
        $employee = Employee::factory()->create($attributes);
        $employee->forceFill(['user_id' => User::factory()->for($this->tenant)->create()->id])->save();

        return $employee;
    }

    private function login(Employee $employee): void
    {
        $this->actingAs(User::findOrFail($employee->user_id));
    }

    private function workflow(ApproverType $type, array $step = []): void
    {
        $workflow = Workflow::create(['approval_type_id' => ApprovalType::forKey('profile_update')?->id, 'name' => 'Profile changes']);
        WorkflowStep::create(['workflow_id' => $workflow->id, 'level' => 1, 'approver_type' => $type, ...$step]);
    }

    /** Rahim asks for a new mobile number. */
    private function rahimRequestsNewMobile(string $mobile = '01811999999'): void
    {
        $this->login($this->rahim);
        Livewire::test(ProfileRequests::class)
            ->call('open')
            ->assertSet('form.mobile', '01711000000')
            ->set('form.mobile', $mobile)
            ->set('reason', 'New SIM')
            ->call('submit')
            ->assertHasNoErrors();
    }

    // ---- The request and its approval ----------------------------------------------------

    public function test_the_approval_type_is_registered_by_the_migration(): void
    {
        $this->assertSame(ProfileUpdateRequest::class, ApprovalType::forKey('profile_update')?->model_class);
    }

    public function test_a_request_stores_only_the_changes_and_goes_to_the_supervisor(): void
    {
        $this->workflow(ApproverType::Supervisor);
        $this->rahimRequestsNewMobile();

        $request = ProfileUpdateRequest::sole();
        $this->assertEquals(['mobile' => ['old' => '01711000000', 'new' => '01811999999']], $request->field_changes);   // MySQL JSON may reorder keys
        $this->assertSame('New SIM', $request->reason);
        $this->assertSame(ApprovalStatus::Pending, $request->approvalStatus());
        $this->assertSame($this->salma->user_id, Approval::sole()->approver_user_id);
        $this->assertSame('01711000000', $this->rahim->refresh()->mobile, 'Nothing changes before approval.');
    }

    public function test_approval_applies_the_change(): void
    {
        $this->workflow(ApproverType::Supervisor);
        $this->rahimRequestsNewMobile();

        // Salma has no HR role and cannot "see" Rahim in HRM, but she approves as his supervisor.
        app(WorkflowEngine::class)->respond(Approval::sole(), ApprovalStatus::Approved, User::findOrFail($this->salma->user_id));

        $this->assertSame('01811999999', $this->rahim->refresh()->mobile);
        $this->assertNotNull(ProfileUpdateRequest::sole()->applied_at);
    }

    public function test_rejection_changes_nothing_and_a_new_request_is_possible(): void
    {
        $this->workflow(ApproverType::Supervisor);
        $this->rahimRequestsNewMobile();

        app(WorkflowEngine::class)->respond(Approval::sole(), ApprovalStatus::Rejected, User::findOrFail($this->salma->user_id), 'Wrong number');

        $this->assertSame('01711000000', $this->rahim->refresh()->mobile);
        $this->assertNull(ProfileUpdateRequest::sole()->applied_at);

        $this->login($this->rahim);
        Livewire::test(ProfileRequests::class)->assertSee('Wrong number');
        $this->rahimRequestsNewMobile('01911888888');
        $this->assertSame(2, ProfileUpdateRequest::count());
    }

    public function test_enum_changes_are_shown_as_labels_to_the_approver(): void
    {
        $this->workflow(ApproverType::Supervisor);
        $this->login($this->rahim);
        Livewire::test(ProfileRequests::class)
            ->call('open')->set('form.marital_status', (string) MaritalStatus::Married->value)->call('submit')
            ->assertHasNoErrors();

        $this->login($this->salma);
        Livewire::test(RespondForm::class)
            ->call('open', Approval::sole()->id)
            ->assertSee('Marital status')
            ->assertSee('Married');
    }

    // ---- Rules ---------------------------------------------------------------------------

    public function test_nothing_changed_or_a_second_open_request_is_refused(): void
    {
        $this->workflow(ApproverType::Supervisor);
        $this->login($this->rahim);

        Livewire::test(ProfileRequests::class)->call('open')->call('submit')->assertHasErrors('reason');

        $this->rahimRequestsNewMobile();
        Livewire::test(ProfileRequests::class)->call('open')->assertDispatched('toast');
        $this->assertSame(1, ProfileUpdateRequest::count());
    }

    public function test_without_a_workflow_the_request_is_not_kept(): void
    {
        $this->login($this->rahim);

        Livewire::test(ProfileRequests::class)
            ->call('open')->set('form.mobile', '01811999999')->call('submit')
            ->assertHasErrors('reason');

        $this->assertSame(0, ProfileUpdateRequest::count());
    }

    public function test_the_same_rules_as_the_hr_form_apply(): void
    {
        $this->workflow(ApproverType::Supervisor);
        $this->salma->update(['national_id' => '111']);
        $this->login($this->rahim);

        Livewire::test(ProfileRequests::class)
            ->call('open')->set('form.national_id', '111')->set('form.first_name', '')->call('submit')
            ->assertHasErrors(['form.national_id' => 'unique', 'form.first_name' => 'required']);
    }

    public function test_company_fields_cannot_be_requested(): void
    {
        $this->workflow(ApproverType::Supervisor);
        $this->login($this->rahim);

        Livewire::test(ProfileRequests::class)
            ->call('open')
            ->set('form.office_email', 'me@acme.test')
            ->set('form.code', 'X-1')
            ->set('form.mobile', '01811999999')
            ->call('submit');

        $this->assertSame(['mobile'], array_keys(ProfileUpdateRequest::sole()->field_changes));
    }

    // ---- Approver types ------------------------------------------------------------------

    public function test_org_head_walks_up_to_a_unit_with_a_head(): void
    {
        $this->workflow(ApproverType::OrgHead);   // Finance has no head → Head Office's head (Boss)
        $this->rahimRequestsNewMobile();

        $this->assertSame($this->boss->user_id, Approval::sole()->approver_user_id);
    }

    public function test_a_specific_employee_can_be_the_approver(): void
    {
        $this->workflow(ApproverType::Employee, ['approver_employee_id' => $this->boss->id]);
        $this->rahimRequestsNewMobile();

        $this->assertSame($this->boss->user_id, Approval::sole()->approver_user_id);
    }

    public function test_a_supervisor_without_a_login_means_nobody_can_approve(): void
    {
        $this->salma->forceFill(['user_id' => null])->save();
        $this->workflow(ApproverType::Supervisor);
        $this->login($this->rahim);

        Livewire::test(ProfileRequests::class)
            ->call('open')->set('form.mobile', '01811999999')->call('submit')
            ->assertHasErrors('reason');
    }

    // ---- Self service pages --------------------------------------------------------------

    public function test_my_profile_opens_the_own_record_and_offers_request_a_change(): void
    {
        $this->login($this->rahim);

        $this->get(route('ess.profile'))->assertRedirect(route('hrm.employees.show', $this->rahim));
        $this->get(route('hrm.employees.show', $this->rahim))
            ->assertOk()
            ->assertSee('Request a change')
            ->assertDontSee('Edit personal details');
    }

    public function test_a_login_without_an_employee_gets_an_explanation(): void
    {
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: []);

        $this->get(route('ess.profile'))->assertOk()->assertSee('not linked to an employee');
        $this->get(route('ess.profile-requests'))->assertOk()->assertSee('not linked to an employee');
    }
}
