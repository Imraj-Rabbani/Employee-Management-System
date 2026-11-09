<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Hrm\Enums\ManpowerRequestType;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\ManpowerRequest;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Enums\ApproverType;
use App\Modules\Workflow\Models\Approval;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowStep;
use App\Modules\Workflow\Services\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 6.2: manpower requests (approvable). [REC-01]
 */
class ManpowerRequestTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private Employee $manager;

    private Employee $boss;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->tenant = $this->actingAsTenantUser()->tenant;

        $sales = OrgUnit::factory()->create(['name' => 'Sales']);
        $this->boss = $this->employeeWithLogin(['first_name' => 'Boss', 'org_unit_id' => $sales->id]);
        $this->manager = $this->employeeWithLogin(['first_name' => 'Karim', 'org_unit_id' => $sales->id, 'supervisor_id' => $this->boss->id]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function employeeWithLogin(array $attributes): Employee
    {
        $employee = Employee::factory()->create($attributes);
        $employee->forceFill(['user_id' => User::factory()->for($this->tenant)->create()->id])->save();

        return $employee;
    }

    private function supervisorWorkflow(): void
    {
        $workflow = Workflow::create(['approval_type_id' => ApprovalType::forKey('manpower_request')?->id, 'name' => 'Manpower']);
        WorkflowStep::create(['workflow_id' => $workflow->id, 'level' => 1, 'approver_type' => ApproverType::Supervisor]);
    }

    public function test_the_approval_type_is_registered_by_the_migration(): void
    {
        $this->assertSame(ManpowerRequest::class, ApprovalType::forKey('manpower_request')?->model_class);
    }

    public function test_a_new_request_is_a_draft_with_all_its_openings(): void
    {
        $request = ManpowerRequest::factory()->create(['headcount' => 3]);

        $this->assertNull($request->approvalStatus());   // never submitted = draft
        $this->assertSame(ManpowerRequestType::New, $request->fresh()->type);
        $this->assertSame(3, $request->openings());
        $this->assertSame(0, ManpowerRequest::approved()->count());
    }

    public function test_the_workflow_uses_the_requesters_supervisor_and_the_monthly_cost(): void
    {
        $request = ManpowerRequest::factory()->create([
            'headcount' => 2, 'salary_max' => 50000, 'requested_by_user_id' => $this->manager->user_id,
        ]);

        $this->assertSame($this->manager->id, $request->approvalContext()['employee_id']);
        $this->assertSame(100000.0, $request->approvalContext()['amount']);

        $this->supervisorWorkflow();
        $request->submitForApproval(User::findOrFail($this->manager->user_id));

        $this->assertSame($this->boss->user_id, Approval::sole()->approver_user_id);
        $this->assertSame(ApprovalStatus::Pending, $request->approvalStatus());
    }

    public function test_approval_sets_approved_at(): void
    {
        $request = ManpowerRequest::factory()->create(['requested_by_user_id' => $this->manager->user_id]);
        $this->supervisorWorkflow();
        $request->submitForApproval(User::findOrFail($this->manager->user_id));

        app(WorkflowEngine::class)->respond(Approval::sole(), ApprovalStatus::Approved, User::findOrFail($this->boss->user_id));

        $this->assertNotNull($request->fresh()->approved_at);
        $this->assertSame([$request->id], ManpowerRequest::approved()->pluck('id')->all());
    }

    public function test_rejection_leaves_it_unapproved(): void
    {
        $request = ManpowerRequest::factory()->create(['requested_by_user_id' => $this->manager->user_id]);
        $this->supervisorWorkflow();
        $request->submitForApproval(User::findOrFail($this->manager->user_id));

        app(WorkflowEngine::class)->respond(Approval::sole(), ApprovalStatus::Rejected, User::findOrFail($this->boss->user_id), 'No budget');

        $this->assertNull($request->fresh()->approved_at);
        $this->assertSame(ApprovalStatus::Rejected, $request->approvalStatus());
    }

    public function test_a_replacement_names_who_is_replaced_and_a_form_cannot_set_approved_at(): void
    {
        $request = ManpowerRequest::factory()->replacing($this->manager)->create();
        $this->assertSame('Karim', $request->replacesEmployee?->first_name);

        $request->update(['approved_at' => now()]);   // not fillable: silently ignored
        $this->assertNull($request->fresh()->approved_at);
    }
}
