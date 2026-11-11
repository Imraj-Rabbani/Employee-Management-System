<?php

namespace Tests\Feature\Payroll;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Payroll\Actions\RequestCorrection;
use App\Modules\Payroll\Enums\AttendanceStatus;
use App\Modules\Payroll\Jobs\ProcessAttendance;
use App\Modules\Payroll\Livewire\Attendance\CorrectionForm;
use App\Modules\Payroll\Livewire\Attendance\CorrectionSettings;
use App\Modules\Payroll\Livewire\Attendance\CorrectionTable;
use App\Modules\Payroll\Livewire\Ess\MyCorrections;
use App\Modules\Payroll\Models\AttendanceCorrection;
use App\Modules\Payroll\Models\AttendanceDay;
use App\Modules\Payroll\Models\PunchLog;
use App\Modules\Payroll\Models\Shift;
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
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 7.6: attendance corrections, asked by HR or the employee, approved through the workflow. [TA-06]
 * The processing rules themselves are unit tested in tests/Unit/AttendanceProcessorTest.
 *
 * Today = Thursday 10 December 2026, 12:00.
 */
class AttendanceCorrectionTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $hr;

    private User $approver;

    private Employee $karim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-10 12:00');
        $this->tenant = $this->createMigratedTenant(['modules' => ['hrm', 'payroll', 'workflow']]);
        $this->hr = $this->actingAsTenantUser(tenant: $this->tenant);   // Administrator

        Shift::factory()->create(['code' => 'G', 'start_time' => '09:00', 'end_time' => '18:00', 'is_default' => true]);
        $this->karim = Employee::factory()->create(['join_date' => '2026-01-01']);

        // One-level workflow, answered by another Administrator.
        $workflow = Workflow::create(['approval_type_id' => ApprovalType::forKey('attendance_correction')?->id, 'name' => 'Corrections']);
        WorkflowStep::create(['workflow_id' => $workflow->id, 'level' => 1, 'approver_type' => ApproverType::Role, 'approver_role_id' => Role::administrator()?->id]);
        $this->approver = User::factory()->for($this->tenant)->create();
        Role::administrator()?->assignTo($this->approver);
    }

    /** @param  array<string, string>  $values */
    private function ask(array $values = [], bool $self = false): \Livewire\Features\SupportTesting\Testable
    {
        $form = Livewire::test(CorrectionForm::class, ['self' => $self])->call('create', $self ? null : $this->karim->id, '2026-12-03');
        foreach (['in' => '09:00', 'out' => '18:00', 'reason' => 'Card forgotten', ...$values] as $field => $value) {
            $form->set($field, $value);
        }

        return $form->call('save');
    }

    private function answer(ApprovalStatus $decision, ?string $comment = null): void
    {
        $pending = Approval::query()->where('status', ApprovalStatus::Pending)->where('approver_user_id', $this->approver->id)->sole();
        app(WorkflowEngine::class)->respond($pending, $decision, $this->approver, $comment);
    }

    // ---- HR -----------------------------------------------------------------------------------

    public function test_hr_asks_a_correction_and_its_approval_reprocesses_the_day(): void
    {
        AttendanceDay::factory()->for($this->karim)->absent()->create(['date' => '2026-12-03']);

        $this->get(route('payroll.attendance.corrections'))->assertOk()->assertSee('New correction')->assertSee('Limits for employees');
        $this->ask()->assertHasNoErrors()->assertDispatched('corrections-saved');

        $correction = AttendanceCorrection::sole();
        $this->assertSame(['2026-12-03 09:00', '2026-12-03 18:00', $this->hr->id], [
            $correction->requested_in_at?->format('Y-m-d H:i'), $correction->requested_out_at?->format('Y-m-d H:i'), $correction->requested_by_user_id,
        ]);
        $this->assertSame(ApprovalStatus::Pending, $correction->approvalStatus());
        $this->assertStringContainsString('COR-0001', $correction->approvalSubject());
        Livewire::test(CorrectionTable::class)->assertSee('COR-0001')->assertSee('Card forgotten');

        // Approved: the day is processed again with the corrected times (the queue runs at once in tests).
        $this->answer(ApprovalStatus::Approved);

        $day = AttendanceDay::query()->whereDate('date', '2026-12-03')->sole();
        $this->assertNotNull($correction->fresh()->approved_at);
        $this->assertSame([AttendanceStatus::Present, '09:00', '18:00', true], [$day->status, $day->in_at?->format('H:i'), $day->out_at?->format('H:i'), $day->is_corrected]);
    }

    public function test_approval_queues_the_day_and_the_rest_of_its_month(): void
    {
        $this->ask()->assertHasNoErrors();
        Queue::fake();

        $this->answer(ApprovalStatus::Approved);

        Queue::assertPushed(ProcessAttendance::class, fn (ProcessAttendance $job) => [$job->employeeId, $job->from, $job->to] === [$this->karim->id, '2026-12-03', '2026-12-10']);
    }

    public function test_the_tab_needs_its_permission_and_only_shows_visible_employees(): void
    {
        $factory = OrgUnit::factory()->create();
        $rahim = Employee::factory()->create(['join_date' => '2026-01-01', 'org_unit_id' => $factory->id]);
        AttendanceCorrection::factory()->for($this->karim)->create(['reason' => 'Karim reason']);
        AttendanceCorrection::factory()->for($rahim)->create(['reason' => 'Rahim reason']);

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['payroll.punch.manage']);
        $this->get(route('payroll.attendance.corrections'))->assertForbidden();
        Livewire::test(CorrectionForm::class)->call('create')->assertForbidden();

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['payroll.correction.manage']);
        Role::where('name', 'like', 'Test role%')->latest('id')->firstOrFail()->update(['org_unit_id' => $factory->id]);
        $this->get(route('payroll.attendance.corrections'))->assertOk()->assertDontSee('Limits for employees');
        Livewire::test(CorrectionTable::class)->assertSee('Rahim reason')->assertDontSee('Karim reason');
        $this->ask()->assertHasErrors('employeeId');   // Karim is outside this user's org unit
    }

    public function test_without_a_workflow_nothing_is_kept(): void
    {
        Workflow::query()->delete();

        $this->ask()->assertHasErrors('reason');

        $this->assertSame(0, AttendanceCorrection::count());
    }

    // ---- Rules --------------------------------------------------------------------------------

    public function test_the_rules_for_everyone(): void
    {
        $this->ask(['in' => '', 'out' => ''])->assertHasErrors('date');
        Livewire::test(CorrectionForm::class)->call('create', $this->karim->id, '2026-12-11')
            ->set('in', '09:00')->set('reason', 'x')->call('save')->assertHasErrors('date');                 // future day
        Livewire::test(CorrectionForm::class)->call('create', $this->karim->id, '2026-12-10')
            ->set('in', '09:00')->set('out', '18:00')->set('reason', 'x')->call('save')->assertHasErrors('date');   // out still to come

        $this->karim->update(['join_date' => '2026-12-05']);
        $this->ask()->assertHasErrors('date');                                                                // not employed yet
        $this->karim->update(['join_date' => '2026-01-01']);

        AttendanceDay::factory()->for($this->karim)->create(['date' => '2026-12-03'])->forceFill(['is_locked' => true])->save();
        $this->ask()->assertHasErrors('date');                                                                // payroll closed
        AttendanceDay::query()->update(['is_locked' => false]);

        $this->ask()->assertHasNoErrors();
        $this->ask(['in' => '08:00'])->assertHasErrors('date');                                               // one open per day
        $this->assertSame(1, AttendanceCorrection::count());
    }

    public function test_out_before_in_is_the_next_morning(): void
    {
        $night = Shift::factory()->create(['code' => 'N', 'start_time' => '22:00', 'end_time' => '06:00']);
        $action = app(RequestCorrection::class);
        $fmt = fn (array $t) => array_map(fn ($at) => $at?->format('Y-m-d H:i'), $t);

        $this->assertSame(['2026-12-03 22:00', '2026-12-04 06:00'], $fmt($action->times($this->karim, ['date' => '2026-12-03', 'in' => '22:00', 'out' => '06:00', 'reason' => ''])));
        // Only out: the next morning only on an overnight shift.
        $this->assertSame([null, '2026-12-03 06:00'], $fmt($action->times($this->karim, ['date' => '2026-12-03', 'in' => '', 'out' => '06:00', 'reason' => ''])));
        $this->karim->update(['shift_id' => $night->id]);
        $this->assertSame([null, '2026-12-04 06:00'], $fmt($action->times($this->karim->fresh(), ['date' => '2026-12-03', 'in' => '', 'out' => '06:00', 'reason' => ''])));
    }

    public function test_the_newest_approved_correction_counts_and_unapproved_ones_do_not(): void
    {
        PunchLog::factory()->for($this->karim)->create(['punched_at' => '2026-12-03 09:30']);
        AttendanceCorrection::factory()->for($this->karim)->approved()->create(['date' => '2026-12-03', 'requested_in_at' => null, 'requested_out_at' => '2026-12-03 17:00']);
        $this->travel(1)->minutes();
        AttendanceCorrection::factory()->for($this->karim)->approved()->create(['date' => '2026-12-03', 'requested_in_at' => null, 'requested_out_at' => '2026-12-03 18:00']);
        AttendanceCorrection::factory()->for($this->karim)->create(['date' => '2026-12-03', 'requested_in_at' => '2026-12-03 07:00', 'requested_out_at' => null]);

        app(AttendanceService::class)->process($this->karim, CarbonImmutable::parse('2026-12-03'), CarbonImmutable::parse('2026-12-03'));

        $day = AttendanceDay::sole();
        $this->assertSame(['09:30', '18:00', 1, true], [$day->in_at?->format('H:i'), $day->out_at?->format('H:i'), $day->punch_count, $day->is_corrected]);
    }

    // ---- Sent back ----------------------------------------------------------------------------

    public function test_a_correction_sent_back_is_changed_and_sent_again(): void
    {
        $this->ask()->assertHasNoErrors();
        $correction = AttendanceCorrection::sole();

        Livewire::test(CorrectionForm::class)->call('edit', $correction->id)->assertDispatched('toast');   // pending: read-only
        $this->answer(ApprovalStatus::Review, 'Out was 17:30');

        Livewire::test(CorrectionForm::class)->call('edit', $correction->id)
            ->assertSet('out', '18:00')
            ->set('out', '17:30')->call('save')->assertHasNoErrors();

        $correction->refresh();
        $this->assertSame(['17:30', ApprovalStatus::Pending], [$correction->requested_out_at?->format('H:i'), $correction->approvalStatus()]);
        $this->assertSame(1, AttendanceCorrection::count());
    }

    // ---- Self Service -------------------------------------------------------------------------

    public function test_an_employee_asks_for_their_own_day_only(): void
    {
        $me = $this->actingAsTenantUser(tenant: $this->tenant, permissions: []);
        $mine = Employee::factory()->create(['join_date' => '2026-01-01', 'user_id' => $me->id]);

        $this->get(route('ess.attendance'))->assertOk()->assertSee('My corrections')->assertSee('Request a correction');

        $this->ask(self: true)->assertHasNoErrors();
        $this->assertSame([$mine->id, $me->id], [AttendanceCorrection::sole()->employee_id, AttendanceCorrection::sole()->requested_by_user_id]);
        Livewire::test(MyCorrections::class)->assertSee('Card forgotten')->assertSee('Pending');

        // Someone else's id sent from the browser: refused.
        Livewire::test(CorrectionForm::class, ['self' => true])->call('create', null, '2026-12-04')
            ->set('employeeId', (string) $this->karim->id)->set('in', '09:00')->set('reason', 'x')
            ->call('save')->assertHasErrors('employeeId');
        // … and an HR form cannot be faked: $self is locked.
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(CorrectionForm::class, ['self' => true])->set('self', false);
    }

    public function test_company_limits_apply_to_self_service_only(): void
    {
        Livewire::test(CorrectionSettings::class)->set('daysBack', 5)->set('monthlyLimit', 1)->call('save')->assertHasNoErrors();
        $this->assertSame([5, 1], [(int) settings(RequestCorrection::DAYS_BACK), (int) settings(RequestCorrection::MONTHLY_LIMIT)]);

        $me = $this->actingAsTenantUser(tenant: $this->tenant, permissions: []);
        Employee::factory()->create(['join_date' => '2026-01-01', 'user_id' => $me->id]);

        $this->ask(self: true)->assertHasErrors('date');                                                 // 3 Dec: more than 5 days back
        Livewire::test(CorrectionForm::class, ['self' => true])->call('create', null, '2026-12-08')
            ->set('in', '09:00')->set('reason', 'x')->call('save')->assertHasNoErrors();
        Livewire::test(CorrectionForm::class, ['self' => true])->call('create', null, '2026-12-09')
            ->set('in', '09:00')->set('reason', 'x')->call('save')->assertHasErrors('date');              // 2nd in December

        // HR is not limited.
        $this->actingAs($this->hr);
        $this->ask()->assertHasNoErrors();
    }

    public function test_a_user_without_an_employee_record_sees_no_corrections(): void
    {
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: []);

        $this->get(route('ess.attendance'))->assertOk()->assertDontSee('My corrections');
    }

    public function test_the_approver_sees_the_day_next_to_the_request(): void
    {
        AttendanceDay::factory()->for($this->karim)->absent()->create(['date' => '2026-12-03']);
        PunchLog::factory()->for($this->karim)->create(['punched_at' => '2026-12-03 09:05']);
        $this->ask(['in' => ''])->assertHasNoErrors();

        $html = view(AttendanceCorrection::sole()->approvalDetailsView(), ['document' => AttendanceCorrection::sole()])->render();

        $this->assertStringContainsString('COR-0001', $html);
        $this->assertStringContainsString('keep the punch', $html);
        $this->assertStringContainsString('Absent', $html);
        $this->assertStringContainsString('09:05', $html);
    }
}
