<?php

namespace Tests\Feature\Payroll;

use App\Core\Support\Money;
use App\Core\Support\Period;
use App\Models\User;
use App\Modules\Hrm\Enums\Religion;
use App\Modules\Hrm\Exports\TemplateSheet;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Actions\CreatePayrollRun;
use App\Modules\Payroll\Actions\ProcessPayrollRun;
use App\Modules\Payroll\Data\NewPayrollRun;
use App\Modules\Payroll\Enums\AmountBasis;
use App\Modules\Payroll\Enums\CalcMethod;
use App\Modules\Payroll\Enums\HeadType;
use App\Modules\Payroll\Enums\PayCycle;
use App\Modules\Payroll\Enums\PayrollCalcStatus;
use App\Modules\Payroll\Enums\PayrollItemSource;
use App\Modules\Payroll\Enums\PayrollRunType;
use App\Modules\Payroll\Imports\RunAmountImporter;
use App\Modules\Payroll\Livewire\PayrollRunAmounts;
use App\Modules\Payroll\Livewire\PayrollRunForm;
use App\Modules\Payroll\Models\EmployeeSalary;
use App\Modules\Payroll\Models\PayrollItem;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\PayrollRunAmount;
use App\Modules\Payroll\Models\SalaryHead;
use App\Modules\Payroll\Services\PayrollRunScope;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * 10.11: head-wise runs (head rule / salary / upload, eligibility) and off-cycle runs (uploaded amounts). December 2026.
 *
 *   Karim  E-001  Islam,    confirmed, joined 2025-01-01, basic 30,000, LFA 5,000 a year in his salary
 *   Salma  E-002  Hinduism, confirmed, joined 2025-01-01, basic 20,000
 *   Rafi   E-003  Islam,    not confirmed, joined 2026-06-01, basic 30,000
 *   Nabil  E-004  Islam,    joined 2026-12-15, basic 30,000
 */
class HeadWiseRunTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private User $admin;

    /** @var array<string, SalaryHead> */
    private array $heads;

    /** @var array<string, Employee> */
    private array $people;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->actingAsTenantUser();
        $this->heads = [
            'basic' => SalaryHead::factory()->create(['code' => 'BASIC', 'is_basic' => true, 'amount' => null, 'sequence' => 1]),
            'festival' => SalaryHead::factory()->create(['code' => 'FB', 'name' => 'Festival bonus', 'calc_method' => CalcMethod::PercentOfBasic, 'rate' => '100', 'amount' => null, 'pay_cycle' => PayCycle::Annual, 'sequence' => 5]),
            'lfa' => SalaryHead::factory()->create(['code' => 'LFA', 'name' => 'Leave fare', 'pay_cycle' => PayCycle::Annual, 'sequence' => 6]),
            'incentive' => SalaryHead::factory()->create(['code' => 'INC', 'name' => 'Incentive', 'sequence' => 7]),
            'recovery' => SalaryHead::factory()->create(['code' => 'REC', 'name' => 'Recovery', 'type' => HeadType::Deduction, 'sequence' => 8]),
            'employer' => SalaryHead::factory()->create(['code' => 'ERPF', 'type' => HeadType::Info, 'sequence' => 9]),
        ];

        $make = fn (string $code, Religion $religion, string $join, ?string $confirmed, string $basic) => tap(
            Employee::factory()->create(['code' => $code, 'religion' => $religion, 'join_date' => $join, 'confirmation_date' => $confirmed]),
            fn (Employee $e) => EmployeeSalary::create(['employee_id' => $e->id, 'salary_head_id' => $this->heads['basic']->id, 'amount' => $basic, 'effective_from' => $join]),
        );
        $this->people = [
            'karim' => $make('E-001', Religion::Islam, '2025-01-01', '2025-07-01', '30000'),
            'salma' => $make('E-002', Religion::Hinduism, '2025-01-01', '2025-07-01', '20000'),
            'rafi' => $make('E-003', Religion::Islam, '2026-06-01', null, '30000'),
            'nabil' => $make('E-004', Religion::Islam, '2026-12-15', null, '30000'),
        ];
        EmployeeSalary::create(['employee_id' => $this->people['karim']->id, 'salary_head_id' => $this->heads['lfa']->id, 'amount' => '5000', 'effective_from' => '2025-01-01']);
    }

    private function makeRun(NewPayrollRun $input): PayrollRun
    {
        return app(CreatePayrollRun::class)->handle($input, $this->admin);
    }

    private function headWise(string $head, AmountBasis $basis, array $more = []): NewPayrollRun
    {
        return new NewPayrollRun(...['period' => Period::of(2026, 12), 'type' => PayrollRunType::HeadWise, 'salaryHeadId' => $this->heads[$head]->id, 'amountBasis' => $basis, ...$more]);
    }

    /** @return array<string, string> employee code => net */
    private function nets(PayrollRun $run): array
    {
        return $run->employees()->with('employee')->get()->mapWithKeys(fn ($r) => [$r->employee->code => $r->net->toDecimal()])->sortKeys()->all();
    }

    public function test_a_festival_bonus_by_the_heads_rule_for_the_eligible_ones(): void
    {
        $run = $this->makeRun($this->headWise('festival', AmountBasis::HeadRule, [
            'religions' => [Religion::Islam->value], 'joinedBefore' => CarbonImmutable::parse('2026-12-01'), 'prorateByService' => true,
        ]));
        $this->assertSame(['E-001', 'E-003'], $run->employees()->with('employee')->get()->pluck('employee.code')->sort()->values()->all());   // Salma: Hindu; Nabil: joined after

        app(ProcessPayrollRun::class)->handle($run);

        // Karim 100 % of basic; Rafi joined 1 June: 214 of 365 days.
        $this->assertSame(['E-001' => '30000.00', 'E-003' => '17589.04'], $this->nets($run));
        $this->assertSame([$this->heads['festival']->id], PayrollItem::where('payroll_run_id', $run->id)->distinct()->pluck('salary_head_id')->all());   // the one head only
        $rafi = $run->employees()->where('employee_id', $this->people['rafi']->id)->sole();
        $this->assertStringContainsString('share of the year', $rafi->warnings[0]);
        $this->assertSame('47589.04', $run->fresh()->total_net->toDecimal());

        // Confirmed only: Rafi is out.
        $scope = app(PayrollRunScope::class)->employees(Period::of(2026, 12), null, [], ['religions' => [Religion::Islam->value], 'confirmed_only' => true]);
        $this->assertSame(['E-001'], $scope->pluck('code')->all());
    }

    public function test_the_same_head_twice_in_a_month_is_fine_for_different_people_only(): void
    {
        $this->makeRun($this->headWise('festival', AmountBasis::HeadRule, ['religions' => [Religion::Islam->value]]));

        $puja = $this->makeRun($this->headWise('festival', AmountBasis::HeadRule, ['religions' => [Religion::Hinduism->value]]));
        $this->assertSame(1, $puja->employee_count);

        $problem = app(CreatePayrollRun::class)->problem($this->headWise('festival', AmountBasis::HeadRule), $this->admin);
        $this->assertStringContainsString('already in', $problem['message']);
    }

    public function test_an_annual_head_from_the_employees_salary(): void
    {
        $run = $this->makeRun($this->headWise('lfa', AmountBasis::Salary));
        app(ProcessPayrollRun::class)->handle($run);

        $this->assertSame('5000.00', $this->nets($run)['E-001']);
        $salma = $run->employees()->where('employee_id', $this->people['salma']->id)->sole();
        $this->assertSame(PayrollCalcStatus::Ok, $salma->calc_status);
        $this->assertSame('0.00', $salma->net->toDecimal());
        $this->assertSame(['Nothing to pay for Leave fare.'], $salma->warnings);
    }

    public function test_a_head_without_a_usable_rule_needs_another_basis(): void
    {
        $problem = app(CreatePayrollRun::class)->problem($this->headWise('lfa', AmountBasis::HeadRule), $this->admin);   // fixed, but no amount set
        $this->assertNull($problem);

        $this->heads['lfa']->update(['calc_method' => CalcMethod::Attendance]);
        $this->assertSame('amount_basis', app(CreatePayrollRun::class)->problem($this->headWise('lfa', AmountBasis::HeadRule), $this->admin)['field']);
    }

    public function test_an_off_cycle_run_pays_uploaded_amounts_all_or_nothing(): void
    {
        $run = $this->makeRun(new NewPayrollRun(Period::of(2026, 12), PayrollRunType::OffCycle));
        $this->assertSame(AmountBasis::Upload, $run->amount_basis);
        $this->assertSame(0, $run->employees()->count());
        $this->assertSame('Upload the amounts first.', app(ProcessPayrollRun::class)->problem($run));

        $importer = app(RunAmountImporter::class);
        $row = fn (string $code, string $head, string $amount) => ['employee_code' => $code, 'head' => $head, 'amount' => $amount, 'note' => ''];

        $this->assertNull($importer->import([
            2 => $row('E-001', 'INC', '2000'),
            3 => $row('E-999', 'INC', '10'),
            4 => $row('E-001', 'INC', '5'),
            5 => $row('E-002', 'ERPF', '10'),
            6 => $row('E-002', 'INC', '0'),
        ], $run, $this->admin));
        $this->assertSame([[3, 'employee_code'], [4, 'employee_code'], [5, 'head'], [6, 'amount']], array_map(fn ($e) => [$e['row'], $e['column']], $importer->errors()));
        $this->assertSame(0, PayrollRunAmount::count());

        $this->assertSame(3, $importer->import([
            2 => $row('E-001', 'INC', '2000'), 3 => $row('e-001', 'Recovery', '500'), 4 => $row('E-002', 'Incentive', '1,000.50'),
        ], $run, $this->admin));
        $this->assertSame(2, $run->employees()->count());

        app(ProcessPayrollRun::class)->handle($run->fresh());
        $this->assertSame(['E-001' => '1500.00', 'E-002' => '1000.50'], $this->nets($run));
        $this->assertSame([PayrollItemSource::Upload], PayrollItem::where('payroll_run_id', $run->id)->get()->pluck('source')->unique()->values()->all());

        // Uploading again replaces everything; numbers are cleared until calculated again.
        $this->assertSame(1, $importer->import([2 => $row('E-002', 'INC', '700')], $run->fresh(), $this->admin));
        $this->assertSame([$this->people['salma']->id], $run->employees()->pluck('employee_id')->all());
        $this->assertSame(PayrollCalcStatus::Pending, $run->employees()->sole()->calc_status);
        $this->assertSame('0.00', $run->fresh()->total_net->toDecimal());
        app(ProcessPayrollRun::class)->handle($run->fresh());
        $this->assertSame(['E-002' => '700.00'], $this->nets($run));
    }

    public function test_a_head_wise_upload_takes_the_runs_head(): void
    {
        $run = $this->makeRun($this->headWise('incentive', AmountBasis::Upload));
        $importer = app(RunAmountImporter::class);

        $this->assertNull($importer->import([2 => ['employee_code' => 'E-001', 'head' => 'REC', 'amount' => '10']], $run, $this->admin));
        $this->assertSame('This run pays Incentive only.', $importer->errors()[0]['message']);

        $this->assertSame(1, $importer->import([2 => ['employee_code' => 'E-001', 'amount' => '2500']], $run, $this->admin));
        $this->assertSame($this->heads['incentive']->id, PayrollRunAmount::sole()->salary_head_id);

        // Another incentive run for December cannot pay Karim again.
        $other = $this->makeRun($this->headWise('incentive', AmountBasis::Upload));
        $this->assertNull($importer->import([2 => ['employee_code' => 'E-001', 'amount' => '100']], $other, $this->admin));
        $this->assertStringContainsString('already in another Incentive run', $importer->errors()[0]['message']);

        $this->assertSame('employee_codes', app(CreatePayrollRun::class)->problem($this->headWise('incentive', AmountBasis::Upload, ['employeeCodes' => ['E-001']]), $this->admin)['field']);
    }

    public function test_the_form_and_the_upload_card(): void
    {
        Livewire::test(PayrollRunForm::class)->call('create')
            ->set('form.month', '12')->set('form.year', '2026')
            ->set('form.run_type', 'head_wise')->assertSee('Amounts from')
            ->set('form.salary_head_id', (string) $this->heads['festival']->id)
            ->set('form.amount_basis', 'head_rule')
            ->set('form.religions', [(string) Religion::Islam->value])
            ->set('form.confirmed_only', true)
            ->call('save')->assertHasNoErrors();

        $run = PayrollRun::sole();
        $this->assertSame([AmountBasis::HeadRule, [1], true], [$run->amount_basis, $run->religions, $run->confirmed_only]);
        $this->assertSame(1, $run->employee_count);   // Karim
        $this->get(route('payroll.runs.show', $run->id))->assertOk()->assertSee('Islam · confirmed only')->assertDontSee('Uploaded amounts');

        $offCycle = $this->makeRun(new NewPayrollRun(Period::of(2026, 12), PayrollRunType::OffCycle));
        $this->get(route('payroll.runs.show', $offCycle->id))->assertOk()->assertSee('Uploaded amounts');
        $this->get(route('payroll.runs.amounts-template', $offCycle->id))->assertOk()->assertDownload($offCycle->code.'-amounts.xlsx');

        $file = UploadedFile::fake()->createWithContent('amounts.xlsx', Excel::raw(new TemplateSheet('Amounts', ['employee_code', 'employee_name', 'head', 'amount', 'note'], [
            ['E-001', 'Karim', 'INC', '1200', 'Q4'],
        ]), ExcelFormat::XLSX));
        Livewire::test(PayrollRunAmounts::class, ['runId' => $offCycle->id])
            ->set('file', $file)->call('upload')->assertHasNoErrors()->assertSet('problems', [])->assertDispatched('payroll-run-refreshed')
            ->assertSee('1 amount')->assertSee('1,200.00');
        $this->assertSame(Money::of('1200')->toDecimal(), PayrollRunAmount::sole()->amount->toDecimal());
    }
}
