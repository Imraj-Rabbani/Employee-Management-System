<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Enums\ManpowerRequestType;
use App\Modules\Hrm\Livewire\Recruitment\ManpowerRequestDetails;
use App\Modules\Hrm\Livewire\Recruitment\ManpowerRequestForm;
use App\Modules\Hrm\Livewire\Recruitment\ManpowerRequestTable;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Hrm\Models\ManpowerRequest;
use App\Modules\Hrm\Models\Position;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Livewire\RespondForm;
use App\Modules\Workflow\Models\Approval;
use App\Modules\Workflow\Services\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\Concerns\MakesWorkflows;
use Tests\TestCase;

/**
 * HRM → Recruitment → Manpower requests (list + New / Edit / Delete as a draft).
 */
class ManpowerRequestPagesTest extends TestCase
{
    use ActsAsTenantUser;
    use MakesWorkflows;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Entity $sales;

    private Entity $finance;

    private Designation $officer;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->admin = $this->actingAsTenantUser();   // Administrator
        $this->tenant = $this->admin->tenant;

        $this->sales = Entity::factory()->create(['name' => 'Sales']);
        $this->finance = Entity::factory()->create(['name' => 'Finance']);
        $this->officer = Designation::factory()->create(['name' => 'Sales Officer']);
    }

    /** A user who may create requests, limited to the Sales unit. */
    private function salesManager(): User
    {
        $user = $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.recruitment.request']);
        Role::where('name', 'like', 'Test role%')->latest('id')->firstOrFail()->update(['entity_id' => $this->sales->id]);

        return $user;
    }

    private function fillValidForm(): Testable
    {
        return Livewire::test(ManpowerRequestForm::class)
            ->dispatch('create-manpower-request')
            ->set('form.entity_id', $this->sales->id)
            ->set('form.designation_id', $this->officer->id)
            ->set('form.headcount', 2)
            ->set('form.salary_min', '30000')
            ->set('form.salary_max', '40000')
            ->set('form.needed_by', now()->addMonth()->toDateString());
    }

    public function test_the_page_opens_from_the_menu_and_needs_the_permission(): void
    {
        $this->get('/dashboard')->assertSee(route('hrm.recruitment'));
        $this->get('/hrm/recruitment/requests')->assertOk()->assertSee(route('hrm.recruitment.setup.factors'));

        $this->salesManager();
        $this->get('/hrm/recruitment/requests')->assertOk()->assertDontSee(route('hrm.recruitment.setup.factors'));

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: []);
        $this->get('/hrm/recruitment/requests')->assertForbidden();
    }

    public function test_a_draft_is_created_with_the_requester(): void
    {
        $this->fillValidForm()
            ->set('form.justification', '')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('manpower-request-saved');

        $request = ManpowerRequest::sole();
        $this->assertSame($this->admin->id, $request->requested_by_user_id);
        $this->assertSame(ManpowerRequestType::New, $request->type);
        $this->assertSame('40000.00', $request->salary_max);
        $this->assertNull($request->justification);
        $this->assertTrue($request->isDraft());
    }

    public function test_field_rules(): void
    {
        $this->fillValidForm()
            ->set('form.salary_max', '20000')                          // below "from"
            ->set('form.needed_by', now()->subDay()->toDateString())  // in the past
            ->set('form.headcount', 0)
            ->call('save')
            ->assertHasErrors(['form.salary_max', 'form.needed_by', 'form.headcount']);

        // A position must belong to the chosen entity.
        $financeSeat = Position::factory()->create(['entity_id' => $this->finance->id]);
        $this->fillValidForm()->set('form.position_id', $financeSeat->id)->call('save')->assertHasErrors(['form.position_id']);

        $this->assertSame(0, ManpowerRequest::count());
    }

    public function test_a_replacement_needs_someone_from_the_chosen_unit(): void
    {
        $leaver = Employee::factory()->inEntity($this->sales)->create();
        $elsewhere = Employee::factory()->inEntity($this->finance)->create();

        $form = $this->fillValidForm()->set('form.type', ManpowerRequestType::Replacement->value);
        $form->call('save')->assertHasErrors(['form.replaces_employee_id']);
        $form->set('form.replaces_employee_id', $elsewhere->id)->call('save')->assertHasErrors(['form.replaces_employee_id']);
        $form->set('form.replaces_employee_id', $leaver->id)->call('save')->assertHasNoErrors();

        $this->assertSame($leaver->id, ManpowerRequest::sole()->replaces_employee_id);
    }

    public function test_changing_the_entity_clears_the_unit_specific_choices(): void
    {
        $leaver = Employee::factory()->inEntity($this->sales)->create();

        $this->fillValidForm()
            ->set('form.type', ManpowerRequestType::Replacement->value)
            ->set('form.replaces_employee_id', $leaver->id)
            ->set('form.entity_id', $this->finance->id)
            ->assertSet('form.replaces_employee_id', '');
    }

    public function test_a_limited_user_sees_and_picks_only_their_units(): void
    {
        $mine = ManpowerRequest::factory()->create(['entity_id' => $this->sales->id]);
        $theirs = ManpowerRequest::factory()->create(['entity_id' => $this->finance->id]);

        $this->salesManager();

        Livewire::test(ManpowerRequestTable::class)
            ->assertSee('MPR-'.str_pad((string) $mine->id, 4, '0', STR_PAD_LEFT))
            ->assertDontSee('MPR-'.str_pad((string) $theirs->id, 4, '0', STR_PAD_LEFT));

        $this->fillValidForm()->set('form.entity_id', $this->finance->id)->call('save')->assertHasErrors(['form.entity_id']);

        // Livewire turns the ModelNotFoundException into a 404 answer.
        Livewire::test(ManpowerRequestForm::class)->dispatch('edit-manpower-request', id: $theirs->id)->assertNotFound();
    }

    public function test_a_draft_can_be_edited_and_deleted(): void
    {
        $request = ManpowerRequest::factory()->create(['entity_id' => $this->sales->id, 'headcount' => 1]);

        Livewire::test(ManpowerRequestForm::class)
            ->dispatch('edit-manpower-request', id: $request->id)
            ->assertSet('form.headcount', 1)
            ->set('form.headcount', 4)
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame(4, $request->fresh()->headcount);

        Livewire::test(ManpowerRequestForm::class)
            ->dispatch('edit-manpower-request', id: $request->id)
            ->call('delete')
            ->assertHasNoErrors()
            ->assertRedirect(route('hrm.recruitment.requests'));   // also leaves the deleted request's page
        $this->assertSame(0, ManpowerRequest::count());
    }

    public function test_a_sent_request_is_locked_unless_sent_back(): void
    {
        $request = ManpowerRequest::factory()->create(['entity_id' => $this->sales->id, 'headcount' => 1]);
        // A one-level workflow answered by the Administrator role. The requester is never their own
        // approver, so a second administrator answers.
        $approver = User::factory()->for($this->tenant)->create();
        Role::administrator()?->assignTo($approver);
        $this->workflowFor('manpower_request', [$approver]);
        $request->submitForApproval($this->admin);

        Livewire::test(ManpowerRequestForm::class)
            ->dispatch('edit-manpower-request', id: $request->id)
            ->assertSee('it can no longer be changed')
            ->set('form.headcount', 9)
            ->call('save')
            ->assertHasErrors(['form.entity_id'])
            ->call('delete')
            ->assertHasErrors(['form.entity_id']);
        $this->assertSame(1, $request->fresh()->headcount);

        // Sent back for changes (Review): editable again, but still not deletable (it has a history).
        app(WorkflowEngine::class)->respond(Approval::sole(), ApprovalStatus::Review, $approver, 'Make it 3');
        Livewire::test(ManpowerRequestForm::class)
            ->dispatch('edit-manpower-request', id: $request->id)
            ->set('form.headcount', 3)
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame(3, $request->fresh()->headcount);
        $this->assertSame(1, Approval::count());
    }

    // ---- 6.2c: the request's page and "Send for approval" ----------------------------------

    /** A second administrator who answers a one-level workflow (Specific People: them). */
    private function adminWorkflow(): User
    {
        $approver = User::factory()->for($this->tenant)->create();
        Role::administrator()?->assignTo($approver);
        $this->workflowFor('manpower_request', [$approver]);

        return $approver;
    }

    public function test_the_page_shows_the_details_and_is_limited_to_visible_units(): void
    {
        $mine = ManpowerRequest::factory()->create(['entity_id' => $this->sales->id, 'designation_id' => $this->officer->id, 'salary_min' => 30000, 'salary_max' => 40000]);
        $theirs = ManpowerRequest::factory()->create(['entity_id' => $this->finance->id]);

        $this->get(route('hrm.recruitment.requests.show', $mine->id))
            ->assertOk()
            ->assertSee($mine->number())
            ->assertSee('Sales Officer')
            ->assertSee('30,000 – 40,000')
            ->assertSee('Send for approval');

        $this->salesManager();
        $this->get(route('hrm.recruitment.requests.show', $mine->id))->assertOk();
        $this->get(route('hrm.recruitment.requests.show', $theirs->id))->assertNotFound();
        Livewire::test(ManpowerRequestDetails::class, ['requestId' => $theirs->id])->assertNotFound();
    }

    public function test_without_a_workflow_it_stays_a_draft_with_a_message(): void
    {
        $request = ManpowerRequest::factory()->create(['entity_id' => $this->sales->id]);

        Livewire::test(ManpowerRequestDetails::class, ['requestId' => $request->id])
            ->call('submit')
            ->assertDispatched('toast', variant: 'danger');

        $this->assertTrue($request->fresh()->isDraft());
    }

    public function test_sending_locks_it_and_the_approver_sees_the_details(): void
    {
        $approver = $this->adminWorkflow();
        $request = ManpowerRequest::factory()->create(['entity_id' => $this->sales->id, 'designation_id' => $this->officer->id]);

        Livewire::test(ManpowerRequestDetails::class, ['requestId' => $request->id])
            ->call('submit')
            ->assertDispatched('toast', variant: 'success')
            ->assertDontSee('Send for approval')   // buttons gone: no longer editable
            ->assertSee('Pending');

        $this->assertSame(ApprovalStatus::Pending, $request->fresh()->approvalStatus());

        // A second "Send" (e.g. from an old browser tab) is refused, no second approval row.
        Livewire::test(ManpowerRequestDetails::class, ['requestId' => $request->id])->call('submit')->assertDispatched('toast', variant: 'error');
        $this->assertSame(1, Approval::count());

        // The approver's modal shows the request's details (ShowsApprovalDetails).
        $this->actingAs($approver);
        Livewire::test(RespondForm::class)
            ->dispatch('open-approval', id: Approval::sole()->id)
            ->assertSee($request->number())
            ->assertSee('Sales Officer');
    }

    public function test_sent_back_it_can_be_changed_and_sent_again(): void
    {
        $approver = $this->adminWorkflow();
        $request = ManpowerRequest::factory()->create(['entity_id' => $this->sales->id]);
        $request->submitForApproval($this->admin);
        app(WorkflowEngine::class)->respond(Approval::sole(), ApprovalStatus::Review, $approver, 'Lower the salary');

        Livewire::test(ManpowerRequestDetails::class, ['requestId' => $request->id])
            ->assertSee('Send again')
            ->call('submit')
            ->assertDispatched('toast', variant: 'success');

        $this->assertSame(ApprovalStatus::Pending, $request->fresh()->approvalStatus());

        app(WorkflowEngine::class)->respond($request->approvals()->where('status', ApprovalStatus::Pending)->sole(), ApprovalStatus::Approved, $approver);
        $this->assertNotNull($request->fresh()->approved_at);
    }
}
