<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Data\LeaveYear;
use App\Modules\Hrm\Enums\LeaveEntryType;
use App\Modules\Hrm\Livewire\Ess\MyLeave;
use App\Modules\Hrm\Livewire\Leave\EncashmentForm;
use App\Modules\Hrm\Livewire\Leave\EncashmentTable;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveBalance;
use App\Modules\Hrm\Models\LeaveEncashment;
use App\Modules\Hrm\Models\LeaveProfile;
use App\Modules\Hrm\Models\LeaveType;
use App\Modules\Hrm\Services\LeaveLedger;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Enums\ApproverType;
use App\Modules\Workflow\Models\Approval;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowStep;
use App\Modules\Workflow\Services\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 8.6: leave encashment → approval → days leave the balance → ready for payroll; withdrawal. [LV-06]
 * Today = 10 December 2026; Karim has 12 days of Earned leave (encashable, at most 10 at a time).
 */
class LeaveEncashmentTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $manager;

    private Employee $karim;

    private LeaveType $earned;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-10 12:00');
        $this->tenant = $this->createMigratedTenant(['modules' => ['hrm', 'payroll', 'workflow']]);
        $this->actingAsTenantUser(tenant: $this->tenant);

        $this->earned = LeaveType::factory()->create(['code' => 'EL', 'name' => 'Earned leave', 'is_encashable' => true, 'encash_limit' => 10]);
        $casual = LeaveType::factory()->create(['code' => 'CL', 'name' => 'Casual leave']);   // not encashable
        $profile = LeaveProfile::factory()->create();
        $profile->syncEntitlements([$this->earned->id => 12, $casual->id => 10]);
        $this->karim = Employee::factory()->create(['join_date' => '2020-01-01', 'leave_profile_id' => $profile->id]);
        app(LeaveLedger::class)->credit($this->karim, $this->earned, LeaveYear::of(2026), 12, LeaveEntryType::Earned);

        $managers = Role::create(['name' => 'Managers']);
        $this->manager = User::factory()->for($this->tenant)->create();
        $managers->assignTo($this->manager);
        $workflow = Workflow::create(['approval_type_id' => ApprovalType::forKey('leave_encashment')?->id, 'name' => 'Encashment']);
        WorkflowStep::create(['workflow_id' => $workflow->id, 'level' => 1, 'approver_type' => ApproverType::Role, 'approver_role_id' => $managers->id]);
    }

    private function ask(string $days = '5', bool $self = false, ?int $typeId = null): Testable
    {
        return Livewire::test(EncashmentForm::class, ['self' => $self])->call('create', $self ? null : $this->karim->id)
            ->set('leaveTypeId', (string) ($typeId ?? $this->earned->id))
            ->set('days', $days)
            ->set('reason', 'School fees')
            ->call('save');
    }

    private function answer(ApprovalStatus $decision): void
    {
        $row = Approval::query()->where('status', ApprovalStatus::Pending)->where('approver_user_id', $this->manager->id)->latest('id')->firstOrFail();
        app(WorkflowEngine::class)->respond($row, $decision, $this->manager, $decision === ApprovalStatus::Approved ? null : 'No');
    }

    private function balance(): float
    {
        return app(LeaveLedger::class)->balance($this->karim, $this->earned, LeaveYear::of(2026));
    }

    public function test_approved_days_leave_the_balance_and_wait_for_payroll(): void
    {
        $this->get(route('hrm.leave.encashments'))->assertOk()->assertSee('Encash leave');

        $this->ask()->assertHasNoErrors()->assertDispatched('encashment-saved');
        $encashment = LeaveEncashment::sole();
        $this->assertSame([2026, 5.0], [$encashment->leave_year, $encashment->days]);
        $this->assertSame(12.0, $this->balance(), 'Nothing leaves before approval.');

        $this->answer(ApprovalStatus::Approved);

        $encashment->refresh();
        $this->assertNotNull($encashment->approved_at);
        $this->assertSame(7.0, $this->balance());
        $this->assertSame(5.0, LeaveBalance::sole()->encashed);
        $this->assertSame([$encashment->id], LeaveEncashment::query()->readyForPayroll()->pluck('id')->all());
        $this->assertSame(['Approved · waiting for payroll', 'success'], $encashment->statusBadge());
        Livewire::test(EncashmentTable::class)->assertSee($encashment->number());

        // Once payroll paid it (Phase 10), it is no longer ready and cannot be withdrawn.
        $encashment->forceFill(['payroll_run_id' => 1, 'paid_at' => now(), 'amount' => 4500])->save();
        $this->assertSame([], LeaveEncashment::query()->readyForPayroll()->pluck('id')->all());
        Livewire::test(EncashmentForm::class)->call('withdraw', $encashment->id)->assertDispatched('toast', variant: 'error');
        $this->assertNull($encashment->fresh()->withdrawn_at);
    }

    public function test_the_rules(): void
    {
        $this->ask('11')->assertHasErrors(['days']);                    // over the limit of 10 at a time
        $this->ask('2.3')->assertHasErrors(['days']);                   // half days only
        $casual = LeaveType::where('code', 'CL')->sole();
        $this->ask('1', typeId: $casual->id)->assertHasErrors(['leaveTypeId']);   // not encashable: not offered

        app(LeaveLedger::class)->debit($this->karim, $this->earned, LeaveYear::of(2026), 9, LeaveEntryType::Taken);
        $this->ask('4')->assertHasErrors(['days']);                     // only 3 left

        $this->ask('2')->assertHasNoErrors();
        $this->ask('1')->assertHasErrors(['leaveTypeId']);              // one waiting per type
        $this->assertSame(1, LeaveEncashment::count());
    }

    public function test_sent_back_it_is_changed_and_rejected_it_takes_nothing(): void
    {
        $this->ask()->assertHasNoErrors();
        $encashment = LeaveEncashment::sole();
        $this->answer(ApprovalStatus::Review);

        Livewire::test(EncashmentForm::class)->call('edit', $encashment->id)->assertSet('days', '5')
            ->set('days', '3')->call('save')->assertHasNoErrors();
        $this->assertSame(3.0, $encashment->fresh()->days);

        $this->answer(ApprovalStatus::Rejected);
        $this->assertNotNull($encashment->fresh()->rejected_at);
        $this->assertSame(12.0, $this->balance());
    }

    public function test_withdrawal_by_the_employee_and_by_hr(): void
    {
        $me = $this->actingAsTenantUser(tenant: $this->tenant, permissions: []);
        $this->karim->forceFill(['user_id' => $me->id])->save();

        $this->get(route('ess.leave'))->assertOk()->assertSee('My leave encashments');
        $this->ask(self: true)->assertHasNoErrors();
        Livewire::test(MyLeave::class)->assertSee(LeaveEncashment::sole()->number())->assertSee('Pending');

        // Waiting: the employee withdraws it; the approver is no longer asked.
        Livewire::test(EncashmentForm::class, ['self' => true])->call('withdraw', LeaveEncashment::sole()->id)->assertDispatched('encashment-saved');
        $this->assertNotNull(LeaveEncashment::sole()->withdrawn_at);
        $this->assertSame(0, Approval::query()->where('status', ApprovalStatus::Pending)->count());

        // Approved: only HR may withdraw it, and the days come back.
        $this->ask(self: true)->assertHasNoErrors();
        $this->answer(ApprovalStatus::Approved);
        $second = LeaveEncashment::latest('id')->firstOrFail();
        Livewire::test(EncashmentForm::class, ['self' => true])->call('withdraw', $second->id)->assertDispatched('toast', variant: 'error');
        $this->assertSame(7.0, $this->balance());

        $this->actingAsTenantUser(tenant: $this->tenant);   // HR
        Livewire::test(EncashmentForm::class)->call('withdraw', $second->id)->assertDispatched('encashment-saved');
        $this->assertSame(12.0, $this->balance());
        $this->assertSame(0.0, LeaveBalance::sole()->encashed);
    }

    public function test_without_a_workflow_nothing_is_kept(): void
    {
        Workflow::query()->update(['is_active' => false]);

        $this->ask()->assertHasErrors(['reason']);
        $this->assertSame(0, LeaveEncashment::count());
    }
}
