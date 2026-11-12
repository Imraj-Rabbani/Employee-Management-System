<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Data\LeaveYear;
use App\Modules\Hrm\Enums\LeaveEntryType;
use App\Modules\Hrm\Livewire\Ess\MyLeave;
use App\Modules\Hrm\Livewire\Leave\LeaveApplicationDetails;
use App\Modules\Hrm\Livewire\Leave\LeaveApplicationForm;
use App\Modules\Hrm\Livewire\Leave\LeaveApplicationTable;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveApplication;
use App\Modules\Hrm\Models\LeaveApplicationDay;
use App\Modules\Hrm\Models\LeaveBalance;
use App\Modules\Hrm\Models\LeaveLedgerEntry;
use App\Modules\Hrm\Models\LeaveProfile;
use App\Modules\Hrm\Models\LeaveType;
use App\Modules\Hrm\Services\LeaveLedger;
use App\Modules\Payroll\Contracts\LeaveCalendar;
use App\Modules\Payroll\Enums\AttendanceStatus;
use App\Modules\Payroll\Enums\Weekday;
use App\Modules\Payroll\Jobs\ProcessAttendance;
use App\Modules\Payroll\Models\AttendanceDay;
use App\Modules\Payroll\Models\Shift;
use App\Modules\Payroll\Models\WeekendProfile;
use App\Modules\Payroll\Services\AttendanceService;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Enums\ApproverType;
use App\Modules\Workflow\Models\Approval;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowStep;
use App\Modules\Workflow\Services\WorkflowEngine;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 8.4: leave applications → approval → ledger debit + attendance; withdrawal. [LV-04]
 *
 * Today = Thursday 10 December 2026. Friday + Saturday are the weekend.
 *     December 2026:  Sun 6  Mon 7  Tue 8  Wed 9  Thu 10  Fri 11  Sat 12  Sun 13  Mon 14  Tue 15  Wed 16
 */
class LeaveApplicationTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $hr;

    private User $manager;

    private Employee $karim;

    private LeaveType $casual;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-10 12:00');
        $this->tenant = $this->createMigratedTenant(['modules' => ['hrm', 'payroll', 'workflow']]);
        $this->hr = $this->actingAsTenantUser(tenant: $this->tenant);   // Administrator

        Shift::factory()->create(['code' => 'G', 'start_time' => '09:00', 'end_time' => '18:00', 'is_default' => true]);
        $this->casual = LeaveType::factory()->create(['code' => 'CL', 'name' => 'Casual leave', 'exclude_holidays' => true, 'allow_half_day' => true]);
        $profile = LeaveProfile::factory()->create(['name' => 'Staff']);
        $profile->syncEntitlements([$this->casual->id => 10]);

        $this->karim = Employee::factory()->create([
            'join_date' => '2024-01-01',
            'leave_profile_id' => $profile->id,
            'weekend_profile_id' => WeekendProfile::factory()->create(['day_one' => Weekday::Friday, 'day_two' => Weekday::Saturday])->id,
        ]);
        app(LeaveLedger::class)->credit($this->karim, $this->casual, LeaveYear::of(2026), 10, LeaveEntryType::Opening);

        $managers = Role::create(['name' => 'Managers']);
        $this->manager = User::factory()->for($this->tenant)->create();
        $managers->assignTo($this->manager);
        $workflow = Workflow::create(['approval_type_id' => ApprovalType::forKey('leave')?->id, 'name' => 'Leave']);
        WorkflowStep::create(['workflow_id' => $workflow->id, 'level' => 1, 'approver_type' => ApproverType::Role, 'approver_role_id' => $managers->id]);
    }

    /** @param  array<string, mixed>  $values */
    private function apply(array $values = [], bool $self = false): Testable
    {
        $form = Livewire::test(LeaveApplicationForm::class, ['self' => $self])->call('create', $self ? null : $this->karim->id);
        foreach (['leaveTypeId' => (string) $this->casual->id, 'fromDate' => '2026-12-14', 'toDate' => '2026-12-16', 'reason' => 'Family visit', ...$values] as $field => $value) {
            $form->set($field, $value);
        }

        return $form->call('save');
    }

    private function answer(ApprovalStatus $decision, ?string $comment = null): void
    {
        $row = Approval::query()->where('status', ApprovalStatus::Pending)->where('approver_user_id', $this->manager->id)->latest('id')->firstOrFail();
        app(WorkflowEngine::class)->respond($row, $decision, $this->manager, $comment ?? ($decision === ApprovalStatus::Approved ? null : 'No'));
    }

    private function balance(): float
    {
        return app(LeaveLedger::class)->balance($this->karim, $this->casual, LeaveYear::of(2026));
    }

    public function test_hr_applies_the_manager_approves_and_the_days_are_taken(): void
    {
        $this->get(route('hrm.leave.applications'))->assertOk()->assertSee('Apply for leave');

        $this->apply()->assertHasNoErrors()->assertDispatched('leave-saved');

        $application = LeaveApplication::sole();
        $this->assertSame(3.0, $application->days);
        $this->assertSame(2026, $application->leave_year);
        $this->assertSame(['2026-12-14', '2026-12-15', '2026-12-16'], $application->leaveDays->map(fn ($d) => $d->date->toDateString())->all());
        $this->assertSame(ApprovalStatus::Pending, $application->approvalStatus());
        $this->assertSame(10.0, $this->balance(), 'Nothing is taken before approval.');

        $this->answer(ApprovalStatus::Approved);

        $application->refresh();
        $this->assertNotNull($application->approved_at);
        $this->assertSame(7.0, $this->balance());
        $this->assertSame(3.0, LeaveBalance::sole()->taken);
        $entry = LeaveLedgerEntry::where('entry_type', LeaveEntryType::Taken)->sole();
        $this->assertSame($application->id, $entry->source_id);
        $this->assertSame('2026-12-14', $entry->entry_date->toDateString());
        Livewire::test(LeaveApplicationTable::class)->assertSee($application->number())->assertSee('Approved');
    }

    public function test_weekends_inside_the_leave_are_not_counted(): void
    {
        $this->apply(['fromDate' => '2026-12-14', 'toDate' => '2026-12-21'])->assertHasNoErrors();   // Mon – Mon, Fri 18 + Sat 19 off

        $this->assertSame(6.0, LeaveApplication::sole()->days);
        $this->assertSame(6, LeaveApplication::sole()->leaveDays()->count());
    }

    public function test_the_form_previews_the_days_and_the_problems(): void
    {
        Livewire::test(LeaveApplicationForm::class)->call('create', $this->karim->id)
            ->set('leaveTypeId', (string) $this->casual->id)
            ->set('fromDate', '2026-12-14')
            ->set('toDate', '2026-12-16')
            ->assertSee('3 days')
            ->assertSee('10 left before, 7 after')
            ->set('toDate', '2026-12-31')
            ->assertSee('Not enough Casual leave left');
    }

    public function test_the_rules_are_shown_under_their_fields(): void
    {
        // Self Service: past leave needs post-facto, and more days than the balance.
        $me = $this->actingAsTenantUser(tenant: $this->tenant, permissions: []);
        $this->karim->forceFill(['user_id' => $me->id])->save();   // user_id is not fillable: logins are linked by EmployeeLogin

        $this->apply(['fromDate' => '2026-12-07', 'toDate' => '2026-12-08'], self: true)->assertHasErrors(['fromDate']);
        $this->apply(['fromDate' => '2026-12-14', 'toDate' => '2027-01-31'], self: true)->assertHasErrors(['toDate']);

        // A type outside the profile is not offered at all.
        $sick = LeaveType::factory()->create(['name' => 'Sick leave']);
        $this->apply(['leaveTypeId' => (string) $sick->id], self: true)->assertHasErrors(['leaveTypeId']);

        $this->assertSame(0, LeaveApplication::count());
    }

    public function test_hr_may_enter_past_leave(): void
    {
        $this->apply(['fromDate' => '2026-12-07', 'toDate' => '2026-12-08'])->assertHasNoErrors();
    }

    public function test_overlapping_leave_is_refused_until_the_first_is_withdrawn(): void
    {
        $this->apply()->assertHasNoErrors();
        $this->apply(['fromDate' => '2026-12-16', 'toDate' => '2026-12-17'])->assertHasErrors(['fromDate']);

        Livewire::test(LeaveApplicationDetails::class)->call('show', LeaveApplication::sole()->id)->call('withdraw')->assertHasNoErrors();

        $this->apply(['fromDate' => '2026-12-16', 'toDate' => '2026-12-17'])->assertHasNoErrors();
    }

    public function test_leave_over_two_leave_years_is_refused(): void
    {
        $this->apply(['fromDate' => '2026-12-28', 'toDate' => '2027-01-03'])->assertHasErrors(['toDate']);
    }

    public function test_a_closed_payroll_day_refuses_leave(): void
    {
        AttendanceDay::factory()->for($this->karim)->create(['date' => '2026-12-14', 'is_locked' => true]);

        $this->apply()->assertHasErrors(['fromDate']);
    }

    public function test_without_a_workflow_nothing_is_kept(): void
    {
        Workflow::query()->update(['is_active' => false]);

        $this->apply()->assertHasErrors(['reason']);
        $this->assertSame(0, LeaveApplication::count());
        $this->assertSame(0, LeaveApplicationDay::count());
    }

    public function test_a_rejected_application_takes_nothing_and_frees_its_days(): void
    {
        $this->apply()->assertHasNoErrors();
        $this->answer(ApprovalStatus::Rejected);

        $this->assertNotNull(LeaveApplication::sole()->rejected_at);
        $this->assertSame(10.0, $this->balance());
        $this->apply()->assertHasNoErrors();   // same days again
    }

    public function test_sent_back_it_is_changed_and_sent_again(): void
    {
        $this->apply()->assertHasNoErrors();
        $application = LeaveApplication::sole();
        $this->answer(ApprovalStatus::Review, 'Only two days please');

        Livewire::test(LeaveApplicationForm::class)->call('edit', $application->id)
            ->assertSet('toDate', '2026-12-16')
            ->set('toDate', '2026-12-15')
            ->call('save')
            ->assertHasNoErrors();

        $application->refresh();
        $this->assertSame(2.0, $application->days);
        $this->assertSame(2, $application->leaveDays()->count());
        $this->assertSame(ApprovalStatus::Pending, $application->approvalStatus());

        // Waiting again: no more changes.
        Livewire::test(LeaveApplicationForm::class)->call('edit', $application->id)->assertNotDispatched('modal-show');
        $this->assertSame(2.0, $application->fresh()->days);
    }

    public function test_withdrawing_a_waiting_application_takes_it_out_of_the_inbox(): void
    {
        $this->apply()->assertHasNoErrors();
        $application = LeaveApplication::sole();

        Livewire::test(LeaveApplicationDetails::class)->call('show', $application->id)
            ->set('withdrawReason', 'Plans changed')
            ->call('withdraw')
            ->assertHasNoErrors()
            ->assertDispatched('leave-saved');

        $application->refresh();
        $this->assertNotNull($application->withdrawn_at);
        $this->assertSame('Plans changed', $application->withdraw_reason);
        $this->assertSame(0, Approval::query()->where('status', ApprovalStatus::Pending)->count());
        $this->assertSame(['Withdrawn', 'neutral'], $application->statusBadge());
        $this->assertSame(10.0, $this->balance());

        // Twice is refused.
        Livewire::test(LeaveApplicationDetails::class)->call('show', $application->id)->call('withdraw')->assertHasErrors(['withdrawReason']);
    }

    public function test_withdrawing_approved_leave_gives_the_days_back(): void
    {
        $me = $this->actingAsTenantUser(tenant: $this->tenant, permissions: []);
        $this->karim->forceFill(['user_id' => $me->id])->save();   // user_id is not fillable: logins are linked by EmployeeLogin
        $this->apply(self: true)->assertHasNoErrors();
        $this->answer(ApprovalStatus::Approved);
        $this->assertSame(7.0, $this->balance());

        // Before it starts the employee may withdraw it themself.
        Livewire::test(LeaveApplicationDetails::class, ['self' => true])->call('show', LeaveApplication::sole()->id)
            ->call('withdraw')->assertHasNoErrors();

        $this->assertSame(10.0, $this->balance());
        $this->assertSame(0.0, LeaveBalance::sole()->taken);
        $this->assertSame(2, LeaveLedgerEntry::where('entry_type', LeaveEntryType::Taken)->count());   // taken + reversal
    }

    public function test_started_leave_is_withdrawn_only_by_hr_and_attendance_is_processed_again(): void
    {
        $this->apply(['fromDate' => '2026-12-07', 'toDate' => '2026-12-08'])->assertHasNoErrors();   // HR enters past leave
        Queue::fake();
        $this->answer(ApprovalStatus::Approved);
        Queue::assertPushed(ProcessAttendance::class, fn (ProcessAttendance $job) => [$job->employeeId, $job->from, $job->to] === [$this->karim->id, '2026-12-07', '2026-12-08']);

        $me = $this->actingAsTenantUser(tenant: $this->tenant, permissions: []);
        $this->karim->forceFill(['user_id' => $me->id])->save();   // user_id is not fillable: logins are linked by EmployeeLogin
        Livewire::test(LeaveApplicationDetails::class, ['self' => true])->call('show', LeaveApplication::sole()->id)
            ->assertSee('ask HR')
            ->call('withdraw')->assertHasErrors(['withdrawReason']);

        $this->actingAs($this->hr);
        Queue::fake();
        Livewire::test(LeaveApplicationDetails::class)->call('show', LeaveApplication::sole()->id)->call('withdraw')->assertHasNoErrors();
        Queue::assertPushed(ProcessAttendance::class);
        $this->assertSame(10.0, $this->balance());
    }

    public function test_approved_leave_shows_in_attendance(): void
    {
        $this->apply(['fromDate' => '2026-12-07', 'toDate' => '2026-12-07'])->assertHasNoErrors();
        $this->answer(ApprovalStatus::Approved);

        $this->assertSame(['2026-12-07' => true], app(LeaveCalendar::class)->leaveDates($this->karim, CarbonImmutable::parse('2026-12-01'), CarbonImmutable::parse('2026-12-31')));

        app(AttendanceService::class)->process($this->karim, CarbonImmutable::parse('2026-12-07'), CarbonImmutable::parse('2026-12-07'));
        $this->assertSame(AttendanceStatus::Leave, AttendanceDay::where('employee_id', $this->karim->id)->whereDate('date', '2026-12-07')->sole()->status);
    }

    public function test_an_attachment_is_kept_and_only_shown_to_those_involved(): void
    {
        // Livewire's fake uploads lose their file type under travelTo(); this test does not need the frozen date.
        $this->travelBack();
        $this->casual->update(['attachment_after_days' => 2]);

        $this->apply()->assertHasErrors(['upload']);   // 3 days: needs one
        $this->apply(['upload' => UploadedFile::fake()->create('certificate.pdf', 100, 'application/pdf')])->assertHasNoErrors();

        $application = LeaveApplication::sole();
        $this->assertSame('certificate.pdf', $application->attachment_name);
        $url = route('hrm.leave.attachment', $application->id);

        $this->get($url)->assertOk();                                   // HR
        $this->actingAs($this->manager)->get($url)->assertOk();         // the approver
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: []);
        $this->get($url)->assertNotFound();                             // anyone else
    }

    public function test_my_leave_requests_in_self_service(): void
    {
        $me = $this->actingAsTenantUser(tenant: $this->tenant, permissions: []);
        $this->karim->forceFill(['user_id' => $me->id])->save();   // user_id is not fillable: logins are linked by EmployeeLogin

        $this->get(route('ess.leave'))->assertOk()->assertSee('My leave balance')->assertSee('Casual leave');
        $this->apply(self: true)->assertHasNoErrors();

        Livewire::test(MyLeave::class)->assertSee('Pending')->assertSee(LeaveApplication::sole()->number());
        $this->get(route('hrm.leave.applications'))->assertForbidden();   // HR page needs hrm.leave.manage
    }
}
