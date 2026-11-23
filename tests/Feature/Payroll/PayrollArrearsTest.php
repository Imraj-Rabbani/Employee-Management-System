<?php

namespace Tests\Feature\Payroll;

use App\Core\Support\Money;
use App\Core\Support\Period;
use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Actions\CalculateEmployeePayroll;
use App\Modules\Payroll\Actions\CreatePayrollRun;
use App\Modules\Payroll\Actions\SetEmployeeSalary;
use App\Modules\Payroll\Data\NewPayrollRun;
use App\Modules\Payroll\Data\SalaryBreakdown;
use App\Modules\Payroll\Data\SalaryLine;
use App\Modules\Payroll\Enums\ArrearReason;
use App\Modules\Payroll\Enums\PayrollItemSource;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Models\PayrollArrear;
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
 * 10.7: the Arrears stage — back-dated salary changes and late joiners paid in a later run, never twice.
 * Salary: basic 30,000 (prorates), medical 1,000 (does not prorate), conveyance 500 (no arrears).
 */
class PayrollArrearsTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private User $admin;

    private Employee $employee;

    /** @var array<string, SalaryHead> */
    private array $heads;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->actingAsTenantUser();
        $this->heads = [
            'basic' => SalaryHead::factory()->create(['code' => 'BASIC', 'is_basic' => true, 'amount' => null, 'sequence' => 1]),
            'medical' => SalaryHead::factory()->create(['code' => 'MED', 'sequence' => 2, 'prorate' => false]),
            'conveyance' => SalaryHead::factory()->create(['code' => 'CONV', 'sequence' => 3, 'arrear_eligible' => false]),
        ];
        $this->employee = Employee::factory()->create(['join_date' => '2025-01-01']);
        $this->salary('2025-01-01', '30000', '1000', '500');
    }

    private function salary(string $from, string $basic, string $medical, string $conveyance, ?Employee $employee = null): void
    {
        $lines = [];
        foreach (['basic' => $basic, 'medical' => $medical, 'conveyance' => $conveyance] as $key => $amount) {
            $lines[] = new SalaryLine($this->heads[$key], $this->heads[$key]->name, Money::of($amount));
        }
        app(SetEmployeeSalary::class)->handle($employee ?? $this->employee, CarbonImmutable::parse($from), SalaryBreakdown::fromLines($lines));
    }

    private function pay(int $month, PayrollRunStatus $status = PayrollRunStatus::Locked, ?Employee $employee = null): PayrollRunEmployee
    {
        $employee ??= $this->employee;
        $run = app(CreatePayrollRun::class)->handle(new NewPayrollRun(Period::of(2026, $month), employeeCodes: [$employee->code]), $this->admin);
        $row = app(CalculateEmployeePayroll::class)->handle($run->employees()->sole())->fresh();
        $run->forceFill(['status' => $status])->save();
        $this->travel(1)->minutes();   // later changes are "after the calculation"

        return $row;
    }

    private function item(PayrollRunEmployee $row, string $head): ?PayrollItem
    {
        return PayrollItem::where('payroll_run_employee_id', $row->id)->where('salary_head_id', $this->heads[$head]->id)->first();
    }

    public function test_a_back_dated_raise_is_paid_for_every_paid_month_once(): void
    {
        $this->pay(10);
        $this->pay(11);
        $this->salary('2026-10-01', '36000', '1200', '600');   // decided in December, valid from October

        $december = $this->pay(12, PayrollRunStatus::Processed);

        $basic = $this->item($december, 'basic');
        $this->assertSame(['48000.00', '36000.00', '12000.00'], [$basic->amount->toDecimal(), $basic->actual->toDecimal(), $basic->arrear->toDecimal()]);
        $this->assertSame(PayrollItemSource::Structure, $basic->source);
        $this->assertSame('400.00', $this->item($december, 'medical')->arrear->toDecimal());    // 200 × 2 months
        $this->assertSame('0.00', $this->item($december, 'conveyance')->arrear->toDecimal());  // not arrear-eligible
        $this->assertSame('50200.00', $december->gross->toDecimal());                          // 36,000 + 1,200 + 600 + 12,400

        $ledger = PayrollArrear::where('payroll_run_employee_id', $december->id)->orderBy('for_period_start')->orderBy('salary_head_id')->get();
        $this->assertCount(4, $ledger);
        $first = $ledger->first();
        $this->assertSame(['2026-10-01', '36000.00', '30000.00', '6000.00', ArrearReason::SalaryChange], [
            $first->for_period_start->toDateString(), $first->due->toDecimal(), $first->paid_before->toDecimal(), $first->amount->toDecimal(), $first->reason,
        ]);

        // Calculating December again: the same, never double.
        $december = app(CalculateEmployeePayroll::class)->handle($december)->fresh();
        $this->assertSame('12000.00', $this->item($december, 'basic')->arrear->toDecimal());
        $this->assertSame(4, PayrollArrear::count());

        // December paid: January has nothing left to pay.
        $december->run->forceFill(['status' => PayrollRunStatus::Locked])->save();
        $january = app(CreatePayrollRun::class)->handle(new NewPayrollRun(Period::of(2027, 1), employeeCodes: [$this->employee->code]), $this->admin);
        $row = app(CalculateEmployeePayroll::class)->handle($january->employees()->sole())->fresh();
        $this->assertSame('0.00', $this->item($row, 'basic')->arrear->toDecimal());
        $this->assertSame(0, PayrollArrear::where('payroll_run_employee_id', $row->id)->count());
    }

    public function test_a_second_back_dated_change_pays_only_the_rest(): void
    {
        $this->pay(10);
        $this->salary('2026-10-01', '33000', '1000', '500');
        $this->pay(11);                                          // pays October's 3,000 and November at 33,000
        $this->salary('2026-10-01', '36000', '1000', '500');   // corrected again, still from October

        $december = $this->pay(12, PayrollRunStatus::Processed);

        // October: due 36,000, paid 30,000 + 3,000 (November's arrear) → 3,000. November: 36,000 − 33,000 → 3,000.
        $this->assertSame('6000.00', $this->item($december, 'basic')->arrear->toDecimal());
    }

    public function test_a_back_dated_cut_is_taken_back_with_a_warning(): void
    {
        $this->pay(11);
        $this->salary('2026-11-01', '27000', '1000', '500');

        $december = $this->pay(12, PayrollRunStatus::Processed);

        $this->assertSame('24000.00', $this->item($december, 'basic')->amount->toDecimal());   // 27,000 − 3,000
        $this->assertSame(['Arrears take back 3,000.00 (a back-dated salary cut).'], $december->warnings);
    }

    public function test_only_runs_that_are_approved_or_later_count_as_paid(): void
    {
        $this->pay(11, PayrollRunStatus::Processed);   // calculated, never approved
        $this->salary('2026-11-01', '36000', '1000', '500');

        $december = $this->pay(12, PayrollRunStatus::Processed);

        $this->assertSame('0.00', $this->item($december, 'basic')->arrear->toDecimal());
    }

    public function test_a_late_joiner_gets_the_first_month_with_the_second(): void
    {
        settings()->set(PayrollSettings::DEFAULT_PAY_SCALE, PayScaleProfile::factory()->create(['threshold_day' => 15, 'deduct_absent' => false])->id);
        $joiner = Employee::factory()->create(['join_date' => '2026-11-20']);
        $this->salary('2026-11-20', '30000', '1000', '500', $joiner);

        $november = $this->pay(11, employee: $joiner);
        $this->assertTrue($november->joiner_deferred);
        $this->assertSame('0.00', $november->net->toDecimal());

        $december = $this->pay(12, PayrollRunStatus::Processed, $joiner);

        $basic = PayrollItem::where('payroll_run_employee_id', $december->id)->where('salary_head_id', $this->heads['basic']->id)->sole();
        $this->assertSame('11000.00', $basic->arrear->toDecimal());     // 30,000 × 11 ÷ 30 (20–30 November)
        $this->assertSame('41000.00', $basic->amount->toDecimal());
        $medical = PayrollItem::where('payroll_run_employee_id', $december->id)->where('salary_head_id', $this->heads['medical']->id)->sole();
        $this->assertSame('1000.00', $medical->arrear->toDecimal());    // does not prorate: the whole month
        $this->assertSame(ArrearReason::LateJoiner, PayrollArrear::where('payroll_run_employee_id', $december->id)->first()->reason);
        $this->assertFalse($december->joiner_deferred);
    }

    public function test_a_pay_scale_without_arrears_pays_none_and_says_so(): void
    {
        $this->employee->forceFill(['pay_scale_profile_id' => PayScaleProfile::factory()->create(['name' => 'Contract', 'pays_arrear' => false])->id])->save();
        $this->pay(11);
        $this->salary('2026-11-01', '36000', '1000', '500');

        $december = $this->pay(12, PayrollRunStatus::Processed);

        $this->assertSame('0.00', $this->item($december, 'basic')->arrear->toDecimal());
        $this->assertSame(['1 earlier period(s) have back pay due: not paid (pay scale profile Contract pays no arrears).'], $december->warnings);
    }
}
