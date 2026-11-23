<?php

namespace Tests\Feature\Payroll;

use App\Core\Support\Period;
use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Actions\CalculateEmployeePayroll;
use App\Modules\Payroll\Actions\CreatePayrollRun;
use App\Modules\Payroll\Data\NewPayrollRun;
use App\Modules\Payroll\Enums\DayType;
use App\Modules\Payroll\Enums\PayrollItemSource;
use App\Modules\Payroll\Livewire\PayrollSettingsForm;
use App\Modules\Payroll\Models\AttendanceDay;
use App\Modules\Payroll\Models\EmployeeSalary;
use App\Modules\Payroll\Models\PayrollItem;
use App\Modules\Payroll\Models\PayrollRunEmployee;
use App\Modules\Payroll\Models\PayScaleProfile;
use App\Modules\Payroll\Models\SalaryHead;
use App\Modules\Payroll\Services\PayrollSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * 10.6: the Overtime stage (approved hours × basic ÷ hours per month × multiplier of the day type) and its settings.
 * Basic 31,200 ÷ 208 = 150 an hour.
 */
class PayrollOvertimeStageTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private User $admin;

    private Employee $employee;

    private SalaryHead $otHead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->actingAsTenantUser();
        $basic = SalaryHead::factory()->create(['code' => 'BASIC', 'is_basic' => true, 'amount' => null, 'sequence' => 1]);
        $this->otHead = SalaryHead::factory()->create(['code' => 'OT', 'name' => 'Overtime', 'sequence' => 9]);
        settings()->set(PayrollSettings::HEADS, ['overtime' => $this->otHead->id]);
        // Only a few days are processed here: keep the absence rules (10.5) out of the way.
        settings()->set(PayrollSettings::DEFAULT_PAY_SCALE, PayScaleProfile::factory()->create(['deduct_absent' => false])->id);

        $this->employee = Employee::factory()->create(['join_date' => '2025-01-01']);
        EmployeeSalary::create(['employee_id' => $this->employee->id, 'salary_head_id' => $basic->id, 'amount' => '31200', 'effective_from' => '2025-01-01']);
    }

    private function overtime(string $date, int $approvedMinutes, DayType $type = DayType::Workday, int $workedMinutes = 0): void
    {
        AttendanceDay::factory()->for($this->employee)->create([
            'date' => $date, 'day_type' => $type, 'ot_minutes' => $workedMinutes ?: $approvedMinutes, 'ot_approved_minutes' => $approvedMinutes,
        ]);
    }

    private function calculate(): PayrollRunEmployee
    {
        $run = app(CreatePayrollRun::class)->handle(new NewPayrollRun(Period::of(2026, 12), employeeCodes: [$this->employee->code]), $this->admin);

        return app(CalculateEmployeePayroll::class)->handle($run->employees()->sole())->fresh();
    }

    private function otItem(PayrollRunEmployee $row): ?PayrollItem
    {
        return PayrollItem::where('payroll_run_employee_id', $row->id)->where('salary_head_id', $this->otHead->id)->first();
    }

    public function test_approved_hours_are_paid_at_the_rate_of_their_day_type(): void
    {
        settings()->set(PayrollSettings::OVERTIME, ['divisor' => 208, 'multipliers' => [1 => '2', 2 => '1.5', 3 => '3', 4 => '3']]);
        $this->overtime('2026-12-03', 120);                      // 2 h workday × 2   = 600
        $this->overtime('2026-12-04', 60);                       // 1 h workday × 2   = 300
        $this->overtime('2026-12-05', 120, DayType::Weekend);    // 2 h weekend × 1.5 = 450
        $this->overtime('2026-12-16', 30, DayType::Festival);    // ½ h festival × 3  = 225
        $this->overtime('2026-12-17', 0, workedMinutes: 240);    // worked, never approved: not paid

        $row = $this->calculate();

        $item = $this->otItem($row);
        $this->assertSame('1575.00', $item->amount->toDecimal());
        $this->assertSame(PayrollItemSource::Overtime, $item->source);
        $this->assertSame('5.50', $row->overtime_hours);
        $this->assertSame('32775.00', $row->gross->toDecimal());
        $this->assertNull($row->warnings);
    }

    public function test_the_defaults_are_208_hours_and_twice_the_rate(): void
    {
        $this->overtime('2026-12-05', 100, DayType::Holiday);   // 100 min × 2 × 150 ÷ 60 = 500

        $this->assertSame('500.00', $this->otItem($this->calculate())->amount->toDecimal());
    }

    public function test_without_an_overtime_head_nothing_is_paid_and_the_reviewer_is_told(): void
    {
        settings()->set(PayrollSettings::HEADS, []);
        $this->overtime('2026-12-03', 90);

        $row = $this->calculate();

        $this->assertSame(1, PayrollItem::where('payroll_run_employee_id', $row->id)->count());   // the basic only
        $this->assertSame('0.00', $row->overtime_hours);
        $this->assertSame(['1.50 h approved overtime not paid: no head is set for Overtime in the payroll settings.'], $row->warnings);
    }

    public function test_only_days_the_employee_works_in_the_period_count(): void
    {
        $this->employee->forceFill(['separation_date' => '2026-12-10'])->save();
        $this->overtime('2026-12-09', 60);
        $this->overtime('2026-11-30', 60);   // last month
        $this->overtime('2026-12-12', 60);   // after leaving (should not exist, but never paid)

        $row = $this->calculate();

        $this->assertSame('1.00', $row->overtime_hours);
        $this->assertSame('300.00', $this->otItem($row)->amount->toDecimal());   // the full basic's rate, not the prorated one
    }

    public function test_the_settings_screen_saves_the_overtime_rate(): void
    {
        Livewire::test(PayrollSettingsForm::class)
            ->assertSet('otDivisor', '208')->assertSet('otMultipliers.1', '2')
            ->set('otDivisor', '0')->set('otMultipliers.2', 'abc')
            ->call('save')->assertHasErrors(['otDivisor', 'otMultipliers.2']);

        Livewire::test(PayrollSettingsForm::class)
            ->set('otDivisor', '240')->set('otMultipliers.2', '1.5')
            ->call('save')->assertHasNoErrors();

        $settings = app(PayrollSettings::class);
        $this->assertSame(240, $settings->overtimeDivisor());
        $this->assertSame('1.5', $settings->overtimeMultiplier(DayType::Weekend));
        $this->assertSame('2', $settings->overtimeMultiplier(DayType::Workday));
    }
}
