<?php

namespace Tests\Feature\Payroll;

use App\Core\Support\Period;
use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveApplication;
use App\Modules\Hrm\Models\LeaveApplicationDay;
use App\Modules\Hrm\Models\LeaveType;
use App\Modules\Payroll\Actions\CalculateEmployeePayroll;
use App\Modules\Payroll\Actions\CreatePayrollRun;
use App\Modules\Payroll\Data\NewPayrollRun;
use App\Modules\Payroll\Enums\AttendanceStatus;
use App\Modules\Payroll\Enums\PayrollCalcStatus;
use App\Modules\Payroll\Models\AttendanceDay;
use App\Modules\Payroll\Models\EmployeeSalary;
use App\Modules\Payroll\Models\PayrollItem;
use App\Modules\Payroll\Models\PayrollRunEmployee;
use App\Modules\Payroll\Models\PayScaleProfile;
use App\Modules\Payroll\Models\SalaryHead;
use App\Modules\Payroll\Services\PayrollSettings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * 10.5: ComputePayableDays — LWP and absent days, the pay scale switches, late joiners after the threshold day.
 * December 2026 = 31 days; salary 31,000 basic (1,000 a day, prorates) + 1,000 medical (does not prorate).
 */
class PayableDaysTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private User $admin;

    private Employee $employee;

    private LeaveType $lwp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->actingAsTenantUser();
        $basic = SalaryHead::factory()->create(['code' => 'BASIC', 'is_basic' => true, 'amount' => null, 'sequence' => 1]);
        $medical = SalaryHead::factory()->create(['code' => 'MED', 'sequence' => 2, 'prorate' => false]);
        $this->lwp = LeaveType::factory()->create(['code' => 'LWP', 'is_lwp' => true]);

        $this->employee = Employee::factory()->create(['join_date' => '2025-01-01']);
        foreach ([[$basic, '31000'], [$medical, '1000']] as [$head, $amount]) {
            EmployeeSalary::create(['employee_id' => $this->employee->id, 'salary_head_id' => $head->id, 'amount' => $amount, 'effective_from' => '2025-01-01']);
        }
    }

    private function calculate(?Employee $employee = null): PayrollRunEmployee
    {
        $employee ??= $this->employee;
        $run = app(CreatePayrollRun::class)->handle(new NewPayrollRun(Period::of(2026, 12), employeeCodes: [$employee->code]), $this->admin);

        return app(CalculateEmployeePayroll::class)->handle($run->employees()->sole())->fresh();
    }

    /** @return array<string, string> */
    private function items(PayrollRunEmployee $row): array
    {
        return PayrollItem::where('payroll_run_employee_id', $row->id)->with('head')->get()
            ->mapWithKeys(fn (PayrollItem $item) => [$item->head->code => $item->amount->toDecimal()])->all();
    }

    /** An application with the given counted days ('Y-m-d' => 1 or 0.5); approved unless told otherwise. */
    private function leave(array $days, ?LeaveType $type = null, string $state = 'approved'): LeaveApplication
    {
        $dates = array_keys($days);
        $application = LeaveApplication::create([
            'employee_id' => $this->employee->id, 'leave_type_id' => ($type ?? $this->lwp)->id,
            'from_date' => min($dates), 'to_date' => max($dates), 'days' => array_sum($days), 'leave_year' => 2026,
        ]);
        $application->forceFill(match ($state) {
            'approved' => ['approved_at' => now()],
            'withdrawn' => ['approved_at' => now(), 'withdrawn_at' => now()],
            'rejected' => ['rejected_at' => now()],
            default => [],
        })->save();
        foreach ($days as $date => $count) {
            LeaveApplicationDay::create(['leave_application_id' => $application->id, 'employee_id' => $this->employee->id, 'date' => $date, 'days' => $count]);
        }

        return $application;
    }

    /** Every December day Present, except the given statuses. */
    private function attendance(array $statuses, int $lastDay = 31): void
    {
        for ($d = 1; $d <= $lastDay; $d++) {
            $date = CarbonImmutable::create(2026, 12, $d)->toDateString();
            AttendanceDay::factory()->for($this->employee)->create(['date' => $date, 'status' => $statuses[$date] ?? AttendanceStatus::Present]);
        }
    }

    private function payScale(array $rules): PayScaleProfile
    {
        $profile = PayScaleProfile::factory()->create($rules);
        $this->employee->forceFill(['pay_scale_profile_id' => $profile->id])->save();

        return $profile;
    }

    public function test_approved_leave_without_pay_is_deducted_and_half_days_count_half(): void
    {
        $this->leave(['2026-12-07' => 1, '2026-12-08' => 1, '2026-12-09' => 0.5]);

        $row = $this->calculate();

        $this->assertSame(PayrollCalcStatus::Ok, $row->calc_status);
        $this->assertSame('2.50', $row->lwp_days);
        $this->assertSame('28.50', $row->payable_days);
        $this->assertSame(['BASIC' => '28500.00', 'MED' => '1000.00'], $this->items($row));   // medical does not prorate
        $this->assertNull($row->warnings);
    }

    public function test_paid_leave_and_withdrawn_or_rejected_lwp_are_not_deducted(): void
    {
        $this->leave(['2026-12-01' => 1], LeaveType::factory()->create(['is_lwp' => false]));
        $this->leave(['2026-12-02' => 1], state: 'withdrawn');
        $this->leave(['2026-12-03' => 1], state: 'rejected');
        $this->leave(['2026-12-04' => 1], state: 'pending');

        $row = $this->calculate();

        $this->assertSame('0.00', $row->lwp_days);
        $this->assertSame('31.00', $row->payable_days);
    }

    public function test_a_pay_scale_that_does_not_deduct_lwp_pays_in_full_with_a_warning(): void
    {
        $this->payScale(['name' => 'Management', 'deduct_lwp' => false]);
        $this->leave(['2026-12-07' => 1, '2026-12-08' => 1]);

        $row = $this->calculate();

        $this->assertSame('0.00', $row->lwp_days);
        $this->assertSame('31.00', $row->payable_days);
        $this->assertSame(['2.00 day(s) of leave without pay not deducted (pay scale profile Management).'], $row->warnings);
    }

    public function test_absent_days_are_deducted_and_an_lwp_day_is_not_counted_twice(): void
    {
        $this->leave(['2026-12-10' => 1]);
        $this->attendance([
            '2026-12-03' => AttendanceStatus::Absent,
            '2026-12-04' => AttendanceStatus::Absent,
            '2026-12-05' => AttendanceStatus::HalfDay,
            '2026-12-06' => AttendanceStatus::Off,
            '2026-12-10' => AttendanceStatus::Absent,   // already LWP
        ]);

        $row = $this->calculate();

        $this->assertSame('2.50', $row->absent_days);
        $this->assertSame('1.00', $row->lwp_days);
        $this->assertSame('27.50', $row->payable_days);
        $this->assertSame('27500.00', $this->items($row)['BASIC']);
        $this->assertNull($row->warnings);
    }

    public function test_unprocessed_days_give_a_warning_and_no_attendance_at_all_means_not_on_attendance(): void
    {
        $this->attendance(['2026-12-02' => AttendanceStatus::Absent], lastDay: 20);

        $row = $this->calculate();
        $this->assertSame('1.00', $row->absent_days);
        $this->assertSame(['11 of 31 days have no processed attendance: they are not counted as absent.'], $row->warnings);

        AttendanceDay::query()->delete();
        $row = app(CalculateEmployeePayroll::class)->handle($row)->fresh();   // calculated again
        $this->assertSame('0.00', $row->absent_days);
        $this->assertNull($row->warnings);
    }

    public function test_the_default_pay_scale_can_switch_absence_off(): void
    {
        settings()->set(PayrollSettings::DEFAULT_PAY_SCALE, PayScaleProfile::factory()->create(['deduct_absent' => false])->id);
        $this->attendance(['2026-12-02' => AttendanceStatus::Absent]);

        $row = $this->calculate();

        $this->assertSame('0.00', $row->absent_days);
        $this->assertSame('31.00', $row->payable_days);
    }

    public function test_a_joiner_after_the_threshold_day_is_paid_next_month_and_one_before_it_now(): void
    {
        $profile = $this->payScale(['threshold_day' => 15]);
        $late = $this->employee;
        $late->forceFill(['join_date' => '2026-12-20'])->save();
        $early = Employee::factory()->create(['join_date' => '2026-12-10', 'pay_scale_profile_id' => $profile->id]);
        EmployeeSalary::where('employee_id', $late->id)->get()->each(fn (EmployeeSalary $s) => EmployeeSalary::create([
            'employee_id' => $early->id, 'salary_head_id' => $s->salary_head_id, 'amount' => $s->amount->toDecimal(), 'effective_from' => '2025-01-01',
        ]));

        $run = app(CreatePayrollRun::class)->handle(new NewPayrollRun(Period::of(2026, 12), employeeCodes: [$late->code, $early->code]), $this->admin);
        $rows = $run->employees->keyBy('employee_id')->map(fn (PayrollRunEmployee $row) => app(CalculateEmployeePayroll::class)->handle($row)->fresh());

        $row = $rows[$late->id];
        $this->assertSame(PayrollCalcStatus::Ok, $row->calc_status);
        $this->assertSame('0.00', $row->payable_days);
        $this->assertSame([], $this->items($row));   // not even the medical, which does not prorate
        $this->assertSame('0.00', $row->net->toDecimal());
        $this->assertStringContainsString('after the threshold day 15', $row->warnings[0]);

        $row = $rows[$early->id];
        $this->assertSame('22.00', $row->payable_days);
        $this->assertSame('22000.00', $this->items($row)['BASIC']);
    }

    public function test_a_whole_month_without_pay_pays_nothing(): void
    {
        $days = [];
        for ($d = 1; $d <= 31; $d++) {
            $days[CarbonImmutable::create(2026, 12, $d)->toDateString()] = 1;
        }
        $this->leave($days);

        $row = $this->calculate();

        $this->assertSame('0.00', $row->payable_days);
        $this->assertSame([], $this->items($row));
        $this->assertSame(['No payable days in '.Period::of(2026, 12)->label().': nothing is paid.'], $row->warnings);
    }
}
