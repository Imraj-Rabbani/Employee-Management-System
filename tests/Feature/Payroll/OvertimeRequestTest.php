<?php

namespace Tests\Feature\Payroll;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Payroll\Livewire\Attendance\OvertimeForm;
use App\Modules\Payroll\Livewire\Attendance\OvertimeTable;
use App\Modules\Payroll\Livewire\Ess\MyOvertime;
use App\Modules\Payroll\Models\AttendanceDay;
use App\Modules\Payroll\Models\OvertimeRequest;
use App\Modules\Payroll\Models\Shift;
use App\Modules\Payroll\Services\AttendanceService;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Enums\ApproverType;
use App\Modules\Workflow\Exceptions\WorkflowException;
use App\Modules\Workflow\Livewire\RespondForm;
use App\Modules\Workflow\Models\Approval;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowStep;
use App\Modules\Workflow\Services\WorkflowEngine;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 7.7: overtime requests with recommended / approved hours, through the workflow. [TA-07]
 * Two levels: the team lead recommends, the manager approves; each enters hours when approving.
 *
 * Today = Thursday 10 December 2026, 12:00.
 */
class OvertimeRequestTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $hr;

    private User $lead;

    private User $manager;

    private Employee $karim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-10 12:00');
        $this->tenant = $this->createMigratedTenant(['modules' => ['hrm', 'payroll', 'workflow']]);
        $this->hr = $this->actingAsTenantUser(tenant: $this->tenant);   // Administrator

        Shift::factory()->create(['code' => 'G', 'start_time' => '09:00', 'end_time' => '18:00', 'is_default' => true]);
        $this->karim = Employee::factory()->create(['join_date' => '2026-01-01']);

        $leads = Role::create(['name' => 'Team leads']);
        $managers = Role::create(['name' => 'Managers']);
        $this->lead = User::factory()->for($this->tenant)->create();
        $this->manager = User::factory()->for($this->tenant)->create();
        $leads->assignTo($this->lead);
        $managers->assignTo($this->manager);

        $workflow = Workflow::create(['approval_type_id' => ApprovalType::forKey('overtime')?->id, 'name' => 'Overtime']);
        WorkflowStep::create(['workflow_id' => $workflow->id, 'level' => 1, 'approver_type' => ApproverType::Role, 'approver_role_id' => $leads->id]);
        WorkflowStep::create(['workflow_id' => $workflow->id, 'level' => 2, 'approver_type' => ApproverType::Role, 'approver_role_id' => $managers->id]);
    }

    /** @param  array<string, string>  $values */
    private function ask(array $values = [], bool $self = false, string $date = '2026-12-03'): Testable
    {
        $form = Livewire::test(OvertimeForm::class, ['self' => $self])->call('create', $self ? null : $this->karim->id, $date);
        foreach (['hours' => '3', 'detail' => 'Month-end closing', ...$values] as $field => $value) {
            $form->set($field, $value);
        }

        return $form->call('save');
    }

    private function answer(User $who, ApprovalStatus $decision, ?float $hours = null, ?string $comment = null): void
    {
        $row = Approval::query()->where('status', ApprovalStatus::Pending)->where('approver_user_id', $who->id)->latest('id')->firstOrFail();
        app(WorkflowEngine::class)->respond($row, $decision, $who, $comment, amount: $hours);
    }

    public function test_the_lead_recommends_the_manager_approves_and_the_day_gets_the_approved_minutes(): void
    {
        AttendanceDay::factory()->for($this->karim)->create(['date' => '2026-12-03', 'ot_minutes' => 170]);

        $this->get(route('payroll.attendance.overtime'))->assertOk()->assertSee('Ask for overtime');
        // Prefilled from the punches: 2 h 50 min → 2.75 (quarter hours, rounded down).
        Livewire::test(OvertimeForm::class)->call('create', $this->karim->id, '2026-12-03')->assertSet('hours', '2.75');
        $this->ask()->assertHasNoErrors()->assertDispatched('overtime-saved');

        $request = OvertimeRequest::sole();
        $this->assertSame(['3.00', null, $this->hr->id, 'G'], [$request->hours, $request->approved_hours, $request->requested_by_user_id, $request->shift?->code]);

        // Level 1: the modal starts with the asked hours; approving needs a number.
        $this->actingAs($this->lead);
        $row = Approval::query()->where('approver_user_id', $this->lead->id)->sole();
        Livewire::test(RespondForm::class)->call('open', $row->id)->assertSet('amount', '3')->assertSee('Hours')
            ->set('amount', '')->call('approve')->assertHasErrors('comment');
        Livewire::test(RespondForm::class)->call('open', $row->id)->set('amount', '2.75')->call('approve')->assertHasNoErrors();
        $this->assertSame(2.75, $request->fresh()->recommendedHours());

        // Level 2: starts with the recommendation; approves 2.5.
        $this->actingAs($this->manager);
        $row = Approval::query()->where('approver_user_id', $this->manager->id)->sole();
        Livewire::test(RespondForm::class)->call('open', $row->id)->assertSet('amount', '2.75')
            ->set('amount', '2.5')->call('approve')->assertHasNoErrors();

        $request->refresh();
        $this->assertSame(['2.75', '2.50'], [$request->recommended_hours, $request->approved_hours]);
        $this->assertNotNull($request->approved_at);
        $this->assertSame(150, AttendanceDay::query()->whereDate('date', '2026-12-03')->sole()->ot_approved_minutes);
        Livewire::test(OvertimeTable::class)->assertSee('OT-0001')->assertSee('2.75')->assertSee('2.5');

        // Reprocessing keeps the approved minutes.
        app(AttendanceService::class)->process($this->karim, CarbonImmutable::parse('2026-12-03'), CarbonImmutable::parse('2026-12-03'));
        $this->assertSame(150, AttendanceDay::query()->whereDate('date', '2026-12-03')->sole()->ot_approved_minutes);
    }

    public function test_the_engine_refuses_to_approve_overtime_without_hours_or_with_too_many(): void
    {
        $this->ask()->assertHasNoErrors();
        $row = Approval::query()->where('approver_user_id', $this->lead->id)->sole();

        foreach ([null, -1.0, 25.0] as $hours) {
            try {
                app(WorkflowEngine::class)->respond($row, ApprovalStatus::Approved, $this->lead, amount: $hours);
                $this->fail('Approved with '.var_export($hours, true));
            } catch (WorkflowException $e) {
                $this->assertStringContainsString('Hours', $e->getMessage());
            }
        }
        $this->assertSame(ApprovalStatus::Pending, $row->fresh()->status);

        // Other approvals never store an amount.
        $this->answer($this->lead, ApprovalStatus::Review, 9, 'Which project?');
        $this->assertNull($row->fresh()->amount);
    }

    public function test_the_rules(): void
    {
        $this->ask(['hours' => '0'])->assertHasErrors('hours');
        $this->ask(['hours' => '25'])->assertHasErrors('hours');
        $this->ask(['hours' => '1.1'])->assertHasErrors('hours');                     // not a quarter hour
        $this->ask(date: '2027-01-11')->assertHasErrors('hours');                     // > 31 days ahead
        $this->ask(date: '2026-12-20')->assertHasNoErrors();                          // planned overtime is fine

        $this->karim->update(['join_date' => '2026-12-05']);
        $this->ask()->assertHasErrors('hours');                                        // not employed yet
        $this->karim->update(['join_date' => '2026-01-01']);

        AttendanceDay::factory()->for($this->karim)->create(['date' => '2026-12-03'])->forceFill(['is_locked' => true])->save();
        $this->ask()->assertHasErrors('hours');                                        // payroll closed
        AttendanceDay::query()->update(['is_locked' => false]);

        $this->ask()->assertHasNoErrors();
        $this->ask(['hours' => '1'])->assertHasErrors('hours');                       // one per day …
        $this->answer($this->lead, ApprovalStatus::Rejected, comment: 'No');
        $this->ask(['hours' => '1'])->assertHasNoErrors();                            // … rejected ones aside
    }

    public function test_sent_back_it_is_changed_and_the_old_recommendation_no_longer_counts(): void
    {
        $this->ask()->assertHasNoErrors();
        $this->answer($this->lead, ApprovalStatus::Approved, 2);
        $this->answer($this->manager, ApprovalStatus::Review, comment: 'Split it');
        $request = OvertimeRequest::sole();

        Livewire::test(OvertimeForm::class)->call('edit', $request->id)->assertSet('hours', '3')
            ->set('hours', '1.5')->call('save')->assertHasNoErrors();

        $request->refresh();
        $this->assertSame(['1.50', ApprovalStatus::Pending, null], [$request->hours, $request->approvalStatus(), $request->recommendedHours()]);
        $this->assertSame(1.5, $request->approverAmountDefault());
    }

    public function test_the_tab_needs_its_permission_and_shows_visible_employees_only(): void
    {
        $factory = OrgUnit::factory()->create();
        $rahim = Employee::factory()->create(['join_date' => '2026-01-01', 'org_unit_id' => $factory->id]);
        OvertimeRequest::factory()->for($this->karim)->create(['detail' => 'Karim work']);
        OvertimeRequest::factory()->for($rahim)->create(['detail' => 'Rahim work']);

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['payroll.punch.manage']);
        $this->get(route('payroll.attendance.overtime'))->assertForbidden();
        Livewire::test(OvertimeForm::class)->call('create')->assertForbidden();

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['payroll.overtime.manage']);
        Role::where('name', 'like', 'Test role%')->latest('id')->firstOrFail()->update(['org_unit_id' => $factory->id]);
        Livewire::test(OvertimeTable::class)->assertSee('Rahim work')->assertDontSee('Karim work');
        $this->ask()->assertHasErrors('employeeId');
    }

    public function test_an_employee_asks_for_their_own_overtime(): void
    {
        $me = $this->actingAsTenantUser(tenant: $this->tenant, permissions: []);
        $mine = Employee::factory()->create(['join_date' => '2026-01-01', 'user_id' => $me->id]);

        $this->get(route('ess.attendance'))->assertOk()->assertSee('My overtime');
        $this->ask(self: true)->assertHasNoErrors();
        $this->assertSame($mine->id, OvertimeRequest::sole()->employee_id);
        Livewire::test(MyOvertime::class)->assertSee('3 h asked')->assertSee('Pending');

        Livewire::test(OvertimeForm::class, ['self' => true])->call('create', null, '2026-12-04')
            ->set('employeeId', (string) $this->karim->id)->set('hours', '1')->set('detail', 'x')
            ->call('save')->assertHasErrors('employeeId');
    }

    public function test_the_approver_sees_the_worked_overtime(): void
    {
        AttendanceDay::factory()->for($this->karim)->create(['date' => '2026-12-03', 'ot_minutes' => 170, 'ot_post_minutes' => 170]);
        $this->ask()->assertHasNoErrors();

        $html = view('payroll::approvals.overtime', ['document' => OvertimeRequest::sole()])->render();

        $this->assertStringContainsString('OT-0001', $html);
        $this->assertStringContainsString('overtime 2:50', $html);
    }
}
