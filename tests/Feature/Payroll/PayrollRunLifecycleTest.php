<?php

namespace Tests\Feature\Payroll;

use App\Core\Support\Money;
use App\Core\Support\Period;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Actions\CalculateEmployeePayroll;
use App\Modules\Payroll\Actions\CreatePayrollRun;
use App\Modules\Payroll\Actions\LockPayrollRun;
use App\Modules\Payroll\Actions\ProcessPayrollRun;
use App\Modules\Payroll\Actions\SubmitPayrollRun;
use App\Modules\Payroll\Data\NewPayrollRun;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Exceptions\PayrollLockedException;
use App\Modules\Payroll\Livewire\PayrollRunForm;
use App\Modules\Payroll\Livewire\PayrollRunSummary;
use App\Modules\Payroll\Models\AttendanceDay;
use App\Modules\Payroll\Models\EmployeeSalary;
use App\Modules\Payroll\Models\PayrollItem;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\SalaryHead;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Enums\ApproverType;
use App\Modules\Workflow\Models\Approval;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowStep;
use App\Modules\Workflow\Services\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * 10.10: processed → submitted (pending) → approved → locked, and back to processed on reject / send back / take
 * back. From pending on the numbers cannot change (policy + guards).
 */
class PayrollRunLifecycleTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $hr;

    private User $approver;

    private Employee $karim;

    private PayrollRun $run;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createMigratedTenant(['modules' => ['hrm', 'payroll', 'workflow']]);
        $this->hr = $this->actingAsTenantUser(tenant: $this->tenant);   // Administrator

        $basic = SalaryHead::factory()->create(['code' => 'BASIC', 'is_basic' => true, 'amount' => null, 'sequence' => 1]);
        $this->karim = Employee::factory()->create(['code' => 'E-001', 'join_date' => '2025-01-01']);
        EmployeeSalary::create(['employee_id' => $this->karim->id, 'salary_head_id' => $basic->id, 'amount' => '30000', 'effective_from' => '2025-01-01']);

        $approvers = Role::create(['name' => 'Payroll approvers']);
        $this->approver = User::factory()->for($this->tenant)->create();
        $approvers->assignTo($this->approver);
        $workflow = Workflow::create(['approval_type_id' => ApprovalType::forKey('payroll_run')?->id, 'name' => 'Payroll', 'allow_review' => true]);
        WorkflowStep::create(['workflow_id' => $workflow->id, 'level' => 1, 'approver_type' => ApproverType::Role, 'approver_role_id' => $approvers->id]);

        $run = app(CreatePayrollRun::class)->handle(new NewPayrollRun(Period::of(2026, 12)), $this->hr);
        $this->run = app(ProcessPayrollRun::class)->handle($run)->fresh();   // sync queue: Processed
    }

    private function answer(ApprovalStatus $decision): void
    {
        $row = Approval::query()->where('status', ApprovalStatus::Pending)->where('approver_user_id', $this->approver->id)->sole();
        app(WorkflowEngine::class)->respond($row, $decision, $this->approver, 'Checked');
    }

    public function test_submit_approve_and_lock(): void
    {
        AttendanceDay::factory()->for($this->karim)->create(['date' => '2026-12-05']);
        AttendanceDay::factory()->for($this->karim)->create(['date' => '2027-01-05']);   // next month: stays open

        Livewire::test(PayrollRunSummary::class, ['runId' => $this->run->id])
            ->assertSee('Submit for approval')->assertDontSee('Lock')
            ->call('submit')->assertDispatched('payroll-run-refreshed');

        $run = $this->run->fresh();
        $this->assertSame(PayrollRunStatus::Pending, $run->status);
        $this->assertSame($this->hr->id, $run->submitted_by_user_id);
        $this->assertSame(ApprovalStatus::Pending, $run->approvalStatus());
        $this->assertStringContainsString('net 30,000.00', Approval::sole()->approvable->approvalSubject());
        Livewire::test(PayrollRunSummary::class, ['runId' => $run->id])->assertSee('Take back')->assertDontSee('Calculate again')->assertDontSee('Submit for approval');

        $this->answer(ApprovalStatus::Approved);
        $this->assertSame(PayrollRunStatus::Approved, $run->fresh()->status);
        $this->assertNotNull($run->fresh()->approved_at);

        Livewire::test(PayrollRunSummary::class, ['runId' => $run->id])->assertSee('Lock')->call('lock');
        $run = $run->fresh();
        $this->assertSame(PayrollRunStatus::Locked, $run->status);
        $this->assertSame($this->hr->id, $run->locked_by_user_id);
        $this->assertTrue(AttendanceDay::whereDate('date', '2026-12-05')->sole()->is_locked);
        $this->assertFalse(AttendanceDay::whereDate('date', '2027-01-05')->sole()->is_locked);

        // Locked is final: no edits, no deleting; only moving on to disbursed.
        $this->expectLocked(fn () => $run->update(['note' => 'changed']));
        $this->expectLocked(fn () => $run->delete());
        $run = $run->fresh();   // the refused note is still dirty on the old object
        $run->moveTo(PayrollRunStatus::Disbursed);
        $this->assertSame(PayrollRunStatus::Disbursed, $run->fresh()->status);
    }

    public function test_from_pending_on_the_numbers_cannot_change(): void
    {
        app(SubmitPayrollRun::class)->handle($this->run);
        $run = $this->run->fresh();
        $row = $run->employees()->sole();

        $this->expectLocked(fn () => $row->forceFill(['net' => Money::of('1')])->save());
        $this->expectLocked(fn () => PayrollItem::where('payroll_run_employee_id', $row->id)->first()->delete());
        $this->expectLocked(fn () => $run->forceFill(['total_net' => Money::of('1')])->save());
        $this->expectLocked(fn () => $run->delete());

        try {
            app(CalculateEmployeePayroll::class)->handle($row);
            $this->fail('A pending run was calculated.');
        } catch (LogicException) {
        }
        $this->assertNotNull(app(ProcessPayrollRun::class)->problem($run));

        // The screens offer none of it.
        Livewire::test(PayrollRunForm::class)->call('edit', $run->id)->set('form.note', 'x')->call('save')->assertHasErrors('form.note');
    }

    public function test_reject_send_back_and_take_back_return_it_to_processed(): void
    {
        $submit = app(SubmitPayrollRun::class);

        $submit->handle($this->run);
        $this->answer(ApprovalStatus::Rejected);
        $this->assertSame(PayrollRunStatus::Processed, $this->run->fresh()->status);

        $submit->handle($this->run->fresh());
        $this->answer(ApprovalStatus::Review);
        $this->assertSame(PayrollRunStatus::Processed, $this->run->fresh()->status);

        $submit->handle($this->run->fresh());
        Livewire::test(PayrollRunSummary::class, ['runId' => $this->run->id])->call('withdraw');
        $this->assertSame(PayrollRunStatus::Processed, $this->run->fresh()->status);
        $this->assertSame(0, Approval::query()->where('status', ApprovalStatus::Pending)->count());

        // Processed again: it can be calculated again and submitted again.
        $this->assertNull(app(ProcessPayrollRun::class)->problem($this->run->fresh()));
        $this->assertNull($submit->problem($this->run->fresh()));
    }

    public function test_what_cannot_be_submitted_or_locked(): void
    {
        $submit = app(SubmitPayrollRun::class);
        $lock = app(LockPayrollRun::class);

        $this->assertNotNull($lock->problem($this->run));   // not approved yet

        $draft = app(CreatePayrollRun::class)->handle(new NewPayrollRun(Period::of(2027, 1)), $this->hr);
        $this->assertStringContainsString('draft', $submit->problem($draft));

        EmployeeSalary::query()->delete();   // Karim fails
        app(ProcessPayrollRun::class)->handle($this->run);
        $this->assertStringContainsString('1 employee is failed', $submit->problem($this->run->fresh()));

        // No workflow for payroll runs: nothing changes.
        EmployeeSalary::create(['employee_id' => $this->karim->id, 'salary_head_id' => SalaryHead::basic()->id, 'amount' => '30000', 'effective_from' => '2025-01-01']);
        app(ProcessPayrollRun::class)->handle($this->run->fresh());
        Workflow::query()->delete();
        Livewire::test(PayrollRunSummary::class, ['runId' => $this->run->id])->call('submit')->assertDispatched('toast');
        $this->assertSame(PayrollRunStatus::Processed, $this->run->fresh()->status);
    }

    public function test_locking_needs_the_approve_permission(): void
    {
        $this->run->forceFill(['status' => PayrollRunStatus::Approved])->save();

        $processor = $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['payroll.run.view', 'payroll.run.process']);
        $this->assertFalse($processor->can('lock', $this->run->fresh()));
        Livewire::test(PayrollRunSummary::class, ['runId' => $this->run->id])->assertDontSee('Lock')->call('lock')->assertForbidden();

        $boss = $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['payroll.run.view', 'payroll.run.approve']);
        $this->assertTrue($boss->can('lock', $this->run->fresh()));
        $this->assertFalse($boss->can('submit', $this->run->fresh()));
    }

    public function test_the_approver_sees_the_totals_and_the_findings(): void
    {
        app(SubmitPayrollRun::class)->handle($this->run);

        $html = view('payroll::approvals.payroll-run', ['document' => $this->run->fresh()])->render();

        $this->assertStringContainsString('30,000.00', $html);
        $this->assertStringContainsString('No bank account', $html);   // a review finding, counted
    }

    private function expectLocked(callable $change): void
    {
        try {
            $change();
            $this->fail('A locked payroll run was changed.');
        } catch (PayrollLockedException) {
            $this->addToAssertionCount(1);
        }
    }
}
