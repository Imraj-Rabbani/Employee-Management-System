<?php

namespace Tests\Feature\Payroll;

use App\Core\Support\Period;
use App\Core\Support\PeriodCycle;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Actions\CalculateEmployeePayroll;
use App\Modules\Payroll\Actions\CreatePayrollRun;
use App\Modules\Payroll\Actions\RequestAdjustment;
use App\Modules\Payroll\Data\NewPayrollRun;
use App\Modules\Payroll\Enums\HeadType;
use App\Modules\Payroll\Enums\PayrollCalcStatus;
use App\Modules\Payroll\Enums\PayrollItemSource;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Imports\AdjustmentImporter;
use App\Modules\Payroll\Livewire\AdjustmentForm;
use App\Modules\Payroll\Livewire\AdjustmentImport;
use App\Modules\Payroll\Livewire\AdjustmentTable;
use App\Modules\Payroll\Models\EmployeeSalary;
use App\Modules\Payroll\Models\PayrollAdjustment;
use App\Modules\Payroll\Models\PayrollItem;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\PayrollRunEmployee;
use App\Modules\Payroll\Models\SalaryHead;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Enums\ApproverType;
use App\Modules\Workflow\Models\Approval;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowStep;
use App\Modules\Workflow\Services\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Modules\Hrm\Exports\TemplateSheet;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * 10.6: payroll adjustments — asking (form, import), approval, withdrawing, and the Adjustments stage of the run.
 * Today = 10 December 2026. Salary: basic 30,000.
 */
class PayrollAdjustmentTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $hr;

    private User $approver;

    private Employee $karim;

    /** @var array<string, SalaryHead> */
    private array $heads;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-10 12:00');
        $this->tenant = $this->createMigratedTenant(['modules' => ['hrm', 'payroll', 'workflow']]);
        $this->hr = $this->actingAsTenantUser(tenant: $this->tenant);   // Administrator

        $this->heads = [
            'basic' => SalaryHead::factory()->create(['code' => 'BASIC', 'is_basic' => true, 'amount' => null, 'sequence' => 1]),
            'shift' => SalaryHead::factory()->create(['code' => 'SHIFT', 'name' => 'Shift allowance', 'sequence' => 2]),
            'canteen' => SalaryHead::factory()->create(['code' => 'CANTEEN', 'name' => 'Canteen', 'type' => HeadType::Deduction, 'sequence' => 3]),
            'info' => SalaryHead::factory()->create(['code' => 'ERPF', 'type' => HeadType::Info, 'sequence' => 4]),
        ];
        $this->karim = Employee::factory()->create(['code' => 'E-1001', 'join_date' => '2025-01-01']);
        EmployeeSalary::create(['employee_id' => $this->karim->id, 'salary_head_id' => $this->heads['basic']->id, 'amount' => '30000', 'effective_from' => '2025-01-01']);

        $approvers = Role::create(['name' => 'Payroll approvers']);
        $this->approver = User::factory()->for($this->tenant)->create();
        $approvers->assignTo($this->approver);
        $workflow = Workflow::create(['approval_type_id' => ApprovalType::forKey('payroll_adjustment')?->id, 'name' => 'Adjustments']);
        WorkflowStep::create(['workflow_id' => $workflow->id, 'level' => 1, 'approver_type' => ApproverType::Role, 'approver_role_id' => $approvers->id]);
    }

    private function approveAll(): void
    {
        foreach (Approval::query()->where('status', ApprovalStatus::Pending)->get() as $row) {
            app(WorkflowEngine::class)->respond($row, ApprovalStatus::Approved, $this->approver);
        }
    }

    private function adjustment(string $head, string $amount, int $month = 12, array $state = ['approved_at' => '2026-12-01']): PayrollAdjustment
    {
        $adjustment = PayrollAdjustment::factory()->create([
            'employee_id' => $this->karim->id, 'salary_head_id' => $this->heads[$head]->id, 'month' => $month, 'amount' => $amount,
        ]);
        $adjustment->forceFill($state)->save();

        return $adjustment;
    }

    private function makeRun(PeriodCycle $cycle = PeriodCycle::Monthly): PayrollRun
    {
        return app(CreatePayrollRun::class)->handle(new NewPayrollRun(Period::of(2026, 12, $cycle), employeeCodes: [$this->karim->code]), $this->hr);
    }

    private function calculate(PayrollRun $run): PayrollRunEmployee
    {
        return app(CalculateEmployeePayroll::class)->handle($run->employees()->sole())->fresh();
    }

    /** @return array<string, string> head code => amount of the adjustment items */
    private function adjustmentItems(PayrollRunEmployee $row): array
    {
        return PayrollItem::where('payroll_run_employee_id', $row->id)->where('source', PayrollItemSource::Adjustment->value)->with('head')->get()
            ->mapWithKeys(fn (PayrollItem $item) => [$item->head->code => $item->amount->toDecimal()])->all();
    }

    public function test_hr_asks_the_approver_approves_and_the_run_pays_it(): void
    {
        $this->get(route('payroll.adjustments'))->assertOk()->assertSee('Payroll adjustments');

        Livewire::test(AdjustmentForm::class)->call('create')
            ->assertSet('year', '2026')->assertSet('month', '12')
            ->set('employeeId', (string) $this->karim->id)->set('headId', (string) $this->heads['shift']->id)
            ->set('amount', '2,000')->set('reason', 'Night shifts')
            ->call('save')->assertHasNoErrors()->assertDispatched('adjustment-saved');

        $adjustment = PayrollAdjustment::sole();
        $this->assertSame(['2000.00', 2026, 12, $this->hr->id], [$adjustment->amount->toDecimal(), $adjustment->year, $adjustment->month, $adjustment->requested_by_user_id]);
        $this->assertSame(ApprovalStatus::Pending, $adjustment->approvalStatus());
        Livewire::test(AdjustmentTable::class)->assertSee('AD-0001')->assertSee('+2,000.00')->assertSee('Waiting');

        $this->approveAll();
        $this->assertNotNull($adjustment->fresh()->approved_at);

        $run = $this->makeRun();
        $row = $this->calculate($run);

        $this->assertSame(['SHIFT' => '2000.00'], $this->adjustmentItems($row));
        $this->assertSame('32000.00', $row->gross->toDecimal());
        $this->assertSame($run->id, $adjustment->fresh()->payroll_run_id);
        Livewire::test(AdjustmentTable::class)->assertSee('In '.$run->code);
    }

    public function test_the_stage_pays_earnings_and_deductions_of_the_month_once(): void
    {
        $this->adjustment('shift', '2000');
        $this->adjustment('canteen', '500');
        $this->adjustment('shift', '111', month: 11);                                       // another month
        $this->adjustment('shift', '222', state: []);                                       // not approved
        $this->adjustment('shift', '333', state: ['approved_at' => now(), 'withdrawn_at' => now()]);

        $run = $this->makeRun();
        $row = $this->calculate($run);

        $this->assertSame(['SHIFT' => '2000.00', 'CANTEEN' => '500.00'], $this->adjustmentItems($row));
        $this->assertSame('32000.00', $row->gross->toDecimal());
        $this->assertSame('500.00', $row->deductions->toDecimal());
        $this->assertSame('31500.00', $row->net->toDecimal());
        $this->assertSame(2, PayrollAdjustment::where('payroll_run_id', $run->id)->count());

        // Calculating again keeps them (taken by THIS run); no double items.
        $row = $this->calculate($run);
        $this->assertCount(2, $this->adjustmentItems($row));
        $this->assertSame(2, PayrollAdjustment::where('payroll_run_id', $run->id)->count());
    }

    public function test_the_first_half_month_run_takes_them_and_the_second_does_not(): void
    {
        $this->adjustment('shift', '2000');

        $first = $this->makeRun(PeriodCycle::FirstHalf);
        $this->assertSame(['SHIFT' => '2000.00'], $this->adjustmentItems($this->calculate($first)));

        $second = $this->makeRun(PeriodCycle::SecondHalf);
        $this->assertSame([], $this->adjustmentItems($this->calculate($second)));
    }

    public function test_a_failed_calculation_or_a_deleted_run_frees_them(): void
    {
        $adjustment = $this->adjustment('shift', '2000');
        $run = $this->makeRun();
        $this->calculate($run);
        $this->assertSame($run->id, $adjustment->fresh()->payroll_run_id);

        EmployeeSalary::query()->delete();   // no salary → Failed
        $this->assertSame(PayrollCalcStatus::Failed, $this->calculate($run)->calc_status);
        $this->assertNull($adjustment->fresh()->payroll_run_id);

        EmployeeSalary::create(['employee_id' => $this->karim->id, 'salary_head_id' => $this->heads['basic']->id, 'amount' => '30000', 'effective_from' => '2025-01-01']);
        $this->calculate($run);
        $run->delete();
        $this->assertNull($adjustment->fresh()->payroll_run_id);
    }

    public function test_deductions_bigger_than_the_gross_give_a_warning(): void
    {
        $this->adjustment('canteen', '30500');

        $row = $this->calculate($this->makeRun());

        $this->assertSame('-500.00', $row->net->toDecimal());
        $this->assertStringContainsString('Net pay is negative', $row->warnings[0]);
    }

    public function test_what_cannot_be_asked(): void
    {
        $action = app(RequestAdjustment::class);
        $ok = ['year' => 2026, 'month' => 12, 'amount' => '100', 'reason' => 'x'];
        $field = fn (string $head, array $values = [], ?Employee $employee = null) => $action->problem($employee ?? $this->karim, $this->heads[$head], [...$ok, ...$values])['field'] ?? null;

        $this->assertNull($field('shift'));
        $this->assertNull($field('canteen'));
        $this->assertSame('salary_head_id', $field('basic'));
        $this->assertSame('salary_head_id', $field('info'));
        $this->assertSame('amount', $field('shift', ['amount' => '0']));
        $this->assertSame('amount', $field('shift', ['amount' => '10.005']));
        $this->assertSame('amount', $field('shift', ['amount' => '-5']));
        $this->assertSame('reason', $field('shift', ['reason' => ' ']));
        $this->assertSame('month', $field('shift', ['month' => 13]));
        $this->assertSame('month', $field('shift', [], Employee::factory()->create(['join_date' => '2027-01-05'])));

        $run = $this->makeRun();
        $run->forceFill(['status' => PayrollRunStatus::Locked])->save();
        $this->assertSame('month', $field('shift'));
        $this->assertStringContainsString('is closed', $action->problem($this->karim, $this->heads['shift'], $ok)['message']);
    }

    public function test_withdrawing_until_a_locked_run_took_it(): void
    {
        $a = $this->adjustment('shift', '2000');
        $b = $this->adjustment('canteen', '500');
        $run = $this->makeRun();
        $this->calculate($run);

        // Draft run: may withdraw; the run lets go of it.
        Livewire::test(AdjustmentForm::class)->dispatch('withdraw-adjustment', id: $a->id)->assertDispatched('adjustment-saved');
        $a->refresh();
        $this->assertNotNull($a->withdrawn_at);
        $this->assertNull($a->payroll_run_id);
        $this->assertSame(['CANTEEN' => '500.00'], $this->adjustmentItems($this->calculate($run)));

        // Locked run: no.
        $run->forceFill(['status' => PayrollRunStatus::Locked])->save();
        Livewire::test(AdjustmentForm::class)->dispatch('withdraw-adjustment', id: $b->id)->assertNotDispatched('adjustment-saved');
        $this->assertNull($b->fresh()->withdrawn_at);
    }

    public function test_only_a_sent_back_adjustment_can_be_changed(): void
    {
        Livewire::test(AdjustmentForm::class)->call('create')
            ->set('employeeId', (string) $this->karim->id)->set('headId', (string) $this->heads['shift']->id)
            ->set('amount', '1000')->set('reason', 'Night shifts')->call('save')->assertHasNoErrors();
        $adjustment = PayrollAdjustment::sole();

        Livewire::test(AdjustmentForm::class)->call('edit', $adjustment->id)->assertDispatched('toast');

        $row = Approval::query()->where('status', ApprovalStatus::Pending)->sole();
        app(WorkflowEngine::class)->respond($row, ApprovalStatus::Review, $this->approver, 'Only 800');
        Livewire::test(AdjustmentForm::class)->call('edit', $adjustment->id)->assertSet('amount', '1000.00')
            ->set('amount', '800')->call('save')->assertHasNoErrors();

        $this->assertSame('800.00', $adjustment->fresh()->amount->toDecimal());
        $this->assertSame(ApprovalStatus::Pending, $adjustment->fresh()->approvalStatus());
    }

    public function test_the_import_saves_all_rows_or_none(): void
    {
        $importer = app(AdjustmentImporter::class);
        $row = fn (array $values = []) => ['employee_code' => 'E-1001', 'head' => 'SHIFT', 'year' => 2026, 'month' => 12, 'amount' => '2000', 'reason' => 'Night', ...$values];

        $this->assertNull($importer->import([2 => $row(), 3 => $row(['employee_code' => 'NOPE']), 4 => $row(['head' => 'Canteen', 'amount' => '0'])], $this->hr));
        $this->assertSame([
            ['row' => 3, 'column' => 'employee_code', 'message' => 'Unknown employee code (or not one of your employees).'],
            ['row' => 4, 'column' => 'amount', 'message' => 'Amount: more than 0 (a deduction is still a positive amount; the head makes it a deduction).'],
        ], $importer->errors());
        $this->assertSame(0, PayrollAdjustment::count());

        $this->assertNull($importer->import([2 => ['employee_code' => 'E-1001']], $this->hr));
        $this->assertStringContainsString('Missing column(s)', $importer->errors()[0]['message']);

        // A file: heads by code or name; every row sent for approval.
        $xlsx = Excel::raw(new TemplateSheet('Adjustments', AdjustmentImporter::COLUMNS, [
            ['E-1001', 'SHIFT', 2026, 12, '2000', 'Night shifts'],
            ['e-1001', 'Canteen', 2026, 12, '450.50', 'Canteen bill'],
        ]), ExcelFormat::XLSX);
        $this->travelBack();   // Livewire's temporary upload breaks under a clock frozen in the future
        Livewire::test(AdjustmentImport::class)->call('open')
            ->set('file', UploadedFile::fake()->createWithContent('adjustments.xlsx', $xlsx))
            ->call('import')->assertHasNoErrors()->assertSet('problems', [])->assertDispatched('adjustment-saved');

        $this->assertSame(['2000.00', '450.50'], PayrollAdjustment::orderBy('id')->get()->map(fn ($a) => $a->amount->toDecimal())->all());
        $this->assertSame(2, Approval::query()->where('status', ApprovalStatus::Pending)->count());

        $this->get(route('payroll.adjustments.template'))->assertOk()->assertDownload('payroll-adjustments-template.xlsx');
    }

    public function test_permissions_and_visibility(): void
    {
        $viewer = $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['payroll.adjustment.view']);
        $this->get(route('payroll.adjustments'))->assertOk()->assertDontSee('Add new');
        $this->get(route('payroll.adjustments.template'))->assertForbidden();
        Livewire::test(AdjustmentForm::class)->call('create')->assertForbidden();

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['payroll.run.view']);
        $this->get(route('payroll.adjustments'))->assertForbidden();
        $this->assertNotNull($viewer);
    }
}
