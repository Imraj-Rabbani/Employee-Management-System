<?php

namespace Tests\Feature\Payroll;

use App\Core\Support\Period;
use App\Core\Support\PeriodCycle;
use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Actions\CalculateEmployeePayroll;
use App\Modules\Payroll\Actions\CreatePayrollRun;
use App\Modules\Payroll\Data\NewPayrollRun;
use App\Modules\Payroll\Data\PayrollContext;
use App\Modules\Payroll\Enums\HeadType;
use App\Modules\Payroll\Enums\PayCycle;
use App\Modules\Payroll\Enums\PayrollCalcStatus;
use App\Modules\Payroll\Enums\PayrollItemSource;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Enums\PayrollRunType;
use App\Modules\Payroll\Exceptions\PayrollException;
use App\Modules\Payroll\Models\EmployeeSalary;
use App\Modules\Payroll\Models\PayrollItem;
use App\Modules\Payroll\Models\PayrollRunEmployee;
use App\Modules\Payroll\Models\SalaryHead;
use App\Modules\Payroll\Stages\ComputePayableDays;
use App\Modules\Payroll\Stages\ResolvePeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * 10.3: PayrollContext, the stages (period, salary structure, payable days, earnings, persist) and the per-employee action.
 */
class PayrollPipelineTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private User $admin;

    /** @var array<string, SalaryHead> */
    private array $heads;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->actingAsTenantUser();

        $this->heads = [
            'basic' => SalaryHead::factory()->create(['code' => 'BASIC', 'is_basic' => true, 'amount' => null, 'sequence' => 1]),
            'rent' => SalaryHead::factory()->percentOfBasic('50')->create(['code' => 'RENT', 'sequence' => 2]),
            'medical' => SalaryHead::factory()->create(['code' => 'MED', 'sequence' => 3, 'prorate' => false]),   // paid in full
            'bonus' => SalaryHead::factory()->create(['code' => 'ATT', 'sequence' => 4, 'in_gross' => false]),    // earning outside gross
            'pf' => SalaryHead::factory()->create(['code' => 'PF', 'type' => HeadType::Deduction, 'sequence' => 5]),
            'employer' => SalaryHead::factory()->create(['code' => 'ERPF', 'type' => HeadType::Info, 'sequence' => 6]),
        ];
    }

    /** A salary of 30,000 basic + 15,000 rent + 1,000 medical + 500 bonus − 1,500 PF (+ 2,000 employer PF info). */
    private function giveSalary(Employee $employee, string $from = '2025-01-01', ?string $basic = '30000'): void
    {
        $amounts = ['basic' => $basic, 'rent' => bcdiv($basic, '2', 2), 'medical' => '1000', 'bonus' => '500', 'pf' => '1500', 'employer' => '2000'];
        foreach ($amounts as $key => $amount) {
            EmployeeSalary::create(['employee_id' => $employee->id, 'salary_head_id' => $this->heads[$key]->id, 'amount' => $amount, 'effective_from' => $from]);
        }
    }

    private function makeRow(Employee $employee, Period $period): PayrollRunEmployee
    {
        $run = app(CreatePayrollRun::class)->handle(new NewPayrollRun($period, employeeCodes: [$employee->code]), $this->admin);

        return $run->employees()->sole();
    }

    /** @return array<string, string> head code => amount of the employee's items */
    private function items(PayrollRunEmployee $row): array
    {
        return PayrollItem::where('payroll_run_employee_id', $row->id)->with('head')->get()
            ->mapWithKeys(fn (PayrollItem $item) => [$item->head->code => $item->amount->toDecimal()])->all();
    }

    public function test_a_fixed_salary_with_no_attendance_issues_gets_the_right_items(): void
    {
        $employee = Employee::factory()->create(['join_date' => '2025-01-01']);
        $this->giveSalary($employee);
        $row = $this->makeRow($employee, Period::of(2026, 12));

        $row = app(CalculateEmployeePayroll::class)->handle($row)->fresh();

        $this->assertSame(PayrollCalcStatus::Ok, $row->calc_status);
        $this->assertSame(['BASIC' => '30000.00', 'RENT' => '15000.00', 'MED' => '1000.00', 'ATT' => '500.00', 'PF' => '1500.00', 'ERPF' => '2000.00'], $this->items($row));
        $this->assertSame('31.00', $row->payable_days);
        $this->assertSame('30000.00', $row->basic->toDecimal());
        $this->assertSame('46000.00', $row->gross->toDecimal());       // basic + rent + medical; the bonus is outside gross
        $this->assertSame('1500.00', $row->deductions->toDecimal());
        $this->assertSame('44500.00', $row->net->toDecimal());          // the info head is in neither total
        $this->assertNull($row->warnings);
        $this->assertNull($row->error);
        $this->assertNotNull($row->calculated_at);

        $item = PayrollItem::where('payroll_run_employee_id', $row->id)->whereHas('head', fn ($q) => $q->where('code', 'RENT'))->sole();
        $this->assertSame(PayrollItemSource::Structure, $item->source);
        $this->assertSame('15000.00', $item->actual->toDecimal());
        $this->assertSame('0.00', $item->arrear->toDecimal());
        $this->assertSame($employee->id, $item->employee_id);

        $run = $row->run;
        $run->refreshTotals();
        $this->assertSame('44500.00', $run->fresh()->total_net->toDecimal());
    }

    public function test_calculating_again_replaces_the_items(): void
    {
        $employee = Employee::factory()->create(['join_date' => '2025-01-01']);
        $this->giveSalary($employee);
        $row = $this->makeRow($employee, Period::of(2026, 12));

        app(CalculateEmployeePayroll::class)->handle($row);
        app(CalculateEmployeePayroll::class)->handle($row);

        $this->assertSame(6, PayrollItem::where('payroll_run_employee_id', $row->id)->count());
    }

    public function test_a_joiner_is_paid_for_the_days_worked_except_heads_that_do_not_prorate(): void
    {
        $employee = Employee::factory()->create(['join_date' => '2026-12-11']);   // works 21 of 31 days
        $this->giveSalary($employee, '2026-12-11');
        $row = $this->makeRow($employee, Period::of(2026, 12));

        $row = app(CalculateEmployeePayroll::class)->handle($row)->fresh();

        $this->assertSame('21.00', $row->payable_days);
        $this->assertSame('31.00', $row->period_days);
        $items = $this->items($row);
        $this->assertSame('20322.58', $items['BASIC']);     // 30,000 × 21 ÷ 31
        $this->assertSame('1000.00', $items['MED']);        // prorate = off
        $this->assertSame('1016.13', $items['PF']);         // 1,500 × 21 ÷ 31
        $item = PayrollItem::where('payroll_run_employee_id', $row->id)->whereHas('head', fn ($q) => $q->where('code', 'BASIC'))->sole();
        $this->assertSame('30000.00', $item->actual->toDecimal());   // the full month stays visible
    }

    public function test_a_leaver_is_paid_up_to_the_last_day(): void
    {
        $employee = Employee::factory()->create(['join_date' => '2025-01-01', 'separation_date' => '2026-12-10']);
        $this->giveSalary($employee);
        $row = app(CalculateEmployeePayroll::class)->handle($this->makeRow($employee, Period::of(2026, 12)))->fresh();

        $this->assertSame('10.00', $row->payable_days);
        $this->assertSame('9677.42', $this->items($row)['BASIC']);   // 30,000 × 10 ÷ 31
    }

    public function test_a_half_month_run_pays_half_the_monthly_salary(): void
    {
        $employee = Employee::factory()->create(['join_date' => '2025-01-01']);
        $this->giveSalary($employee);
        $row = app(CalculateEmployeePayroll::class)->handle($this->makeRow($employee, Period::of(2026, 12, PeriodCycle::SecondHalf)))->fresh();

        $this->assertSame('16.00', $row->payable_days);
        $this->assertSame('16.00', $row->period_days);
        $this->assertSame('15000.00', $this->items($row)['BASIC']);
        $this->assertSame('500.00', $this->items($row)['MED']);
    }

    public function test_no_salary_fails_the_employee_and_a_fix_then_calculates_it(): void
    {
        $employee = Employee::factory()->create(['join_date' => '2025-01-01']);
        $row = $this->makeRow($employee, Period::of(2026, 12));

        $row = app(CalculateEmployeePayroll::class)->handle($row)->fresh();
        $this->assertSame(PayrollCalcStatus::Failed, $row->calc_status);
        $this->assertStringContainsString('No salary on', $row->error);
        $this->assertSame([], $this->items($row));

        $this->giveSalary($employee);
        $row = app(CalculateEmployeePayroll::class)->handle($row)->fresh();
        $this->assertSame(PayrollCalcStatus::Ok, $row->calc_status);
        $this->assertNull($row->error);
        $this->assertCount(6, $this->items($row));
    }

    public function test_a_salary_without_a_basic_fails(): void
    {
        $employee = Employee::factory()->create(['join_date' => '2025-01-01']);
        EmployeeSalary::create(['employee_id' => $employee->id, 'salary_head_id' => $this->heads['medical']->id, 'amount' => '1000', 'effective_from' => '2025-01-01']);

        $row = app(CalculateEmployeePayroll::class)->handle($this->makeRow($employee, Period::of(2026, 12)))->fresh();

        $this->assertSame(PayrollCalcStatus::Failed, $row->calc_status);
        $this->assertStringContainsString('has no basic', $row->error);
    }

    public function test_a_salary_that_starts_inside_the_period_is_used_with_a_warning(): void
    {
        $employee = Employee::factory()->create(['join_date' => '2025-01-01']);
        $this->giveSalary($employee, '2025-01-01', '30000');
        EmployeeSalary::where('employee_id', $employee->id)->update(['effective_to' => '2026-12-15']);
        $this->giveSalary($employee, '2026-12-16', '40000');
        $row = $this->makeRow($employee, Period::of(2026, 12));

        $row = app(CalculateEmployeePayroll::class)->handle($row)->fresh();

        $this->assertSame('40000.00', $this->items($row)['BASIC']);
        $this->assertSame(['The salary changed on '.format_date('2026-12-16').': the new salary is used for the whole period.'], $row->warnings);
    }

    public function test_annual_heads_are_left_out_of_the_monthly_salary_with_a_warning(): void
    {
        $employee = Employee::factory()->create(['join_date' => '2025-01-01']);
        $this->giveSalary($employee);
        $this->heads['medical']->update(['pay_cycle' => PayCycle::Annual]);

        $row = app(CalculateEmployeePayroll::class)->handle($this->makeRow($employee, Period::of(2026, 12)))->fresh();

        $this->assertArrayNotHasKey('MED', $this->items($row));
        $this->assertSame('45000.00', $row->gross->toDecimal());
        $this->assertCount(1, $row->warnings);
        $this->assertStringContainsString('paid in a head-wise run', $row->warnings[0]);
    }

    public function test_final_settlement_runs_are_not_calculated_yet(): void
    {
        $employee = Employee::factory()->create(['join_date' => '2025-01-01']);
        $this->giveSalary($employee);
        $run = app(CreatePayrollRun::class)->handle(new NewPayrollRun(Period::of(2026, 12)), $this->admin);
        $run->forceFill(['run_type' => PayrollRunType::FinalSettlement])->save();   // made in Phase 13

        $row = app(CalculateEmployeePayroll::class)->handle($run->employees()->sole()->fresh())->fresh();

        $this->assertSame(PayrollCalcStatus::Failed, $row->calc_status);
        $this->assertSame('Final settlement runs are not calculated yet.', $row->error);
    }

    public function test_a_submitted_run_cannot_be_calculated_again(): void
    {
        $employee = Employee::factory()->create(['join_date' => '2025-01-01']);
        $this->giveSalary($employee);
        $row = $this->makeRow($employee, Period::of(2026, 12));
        $row->run->forceFill(['status' => PayrollRunStatus::Pending])->save();

        $this->expectException(LogicException::class);
        app(CalculateEmployeePayroll::class)->handle($row->fresh());
    }

    public function test_the_stages_work_one_by_one(): void
    {
        $employee = Employee::factory()->create(['join_date' => '2026-12-20', 'separation_date' => '2026-12-25']);
        $row = $this->makeRow($employee, Period::of(2026, 12));
        $ctx = new PayrollContext($row->run, $row, $employee);

        (new ResolvePeriod)->handle($ctx, fn ($c) => $c);
        $this->assertSame(31, $ctx->periodDays);
        $this->assertSame('2026-12-20', $ctx->workFrom->toDateString());
        $this->assertSame('2026-12-25', $ctx->workTo->toDateString());
        $this->assertSame('100', $ctx->cycleShare);

        app(ComputePayableDays::class)->handle($ctx, fn ($c) => $c);
        $this->assertSame(6.0, $ctx->payableDays);

        // Left before the period: the stage refuses.
        $gone = Employee::factory()->create(['join_date' => '2025-01-01', 'separation_date' => '2026-11-30']);
        $this->expectException(PayrollException::class);
        (new ResolvePeriod)->handle(new PayrollContext($row->run, $row, $gone), fn ($c) => $c);
    }
}
