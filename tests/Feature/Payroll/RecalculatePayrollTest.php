<?php

namespace Tests\Feature\Payroll;

use App\Core\Support\Period;
use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Actions\CreatePayrollRun;
use App\Modules\Payroll\Actions\ProcessPayrollRun;
use App\Modules\Payroll\Actions\RecalculatePayrollEmployees;
use App\Modules\Payroll\Data\NewPayrollRun;
use App\Modules\Payroll\Enums\PayrollCalcStatus;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Livewire\PayrollRegister;
use App\Modules\Payroll\Livewire\PayrollRunEmployeeDetail;
use App\Modules\Payroll\Livewire\PayrollRunSummary;
use App\Modules\Payroll\Models\EmployeeSalary;
use App\Modules\Payroll\Models\PayrollAdjustment;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\PayrollRunEmployee;
use App\Modules\Payroll\Models\SalaryHead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * 10.9: calculating again — one or a few employees at once, the whole run (employees brought up to date first),
 * and the snapshot (bank account, holds, leaving) refreshed on every calculation.
 */
class RecalculatePayrollTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private User $admin;

    private SalaryHead $basic;

    /** @var array<string, Employee> */
    private array $people;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->actingAsTenantUser();
        $this->basic = SalaryHead::factory()->create(['code' => 'BASIC', 'is_basic' => true, 'amount' => null, 'sequence' => 1]);
        foreach (['karim' => 'E-001', 'salma' => 'E-002'] as $who => $code) {
            $this->people[$who] = Employee::factory()->create(['code' => $code, 'join_date' => '2025-01-01']);
            $this->salary($this->people[$who], '30000');
        }
    }

    private function salary(Employee $employee, string $basic): void
    {
        EmployeeSalary::where('employee_id', $employee->id)->delete();
        EmployeeSalary::create(['employee_id' => $employee->id, 'salary_head_id' => $this->basic->id, 'amount' => $basic, 'effective_from' => '2025-01-01']);
    }

    /** @param  list<string>  $codes */
    private function makeRun(array $codes = []): PayrollRun
    {
        return app(CreatePayrollRun::class)->handle(new NewPayrollRun(Period::of(2026, 12), employeeCodes: $codes), $this->admin);
    }

    private function rowOf(PayrollRun $run, string $who): PayrollRunEmployee
    {
        return $run->employees()->where('employee_id', $this->people[$who]->id)->sole();
    }

    public function test_one_employee_is_calculated_again_from_the_register(): void
    {
        $run = app(ProcessPayrollRun::class)->handle($this->makeRun());   // sync queue: Processed
        $this->assertSame('60000.00', $run->fresh()->total_net->toDecimal());
        $karimBefore = $this->rowOf($run, 'karim')->calculated_at;

        $this->travel(5)->minutes();
        $this->salary($this->people['salma'], '36000');   // fixed after the calculation

        Livewire::test(PayrollRegister::class, ['runId' => $run->id])
            ->set('selected', [(string) $this->rowOf($run, 'salma')->id])
            ->call('recalculate')
            ->assertDispatched('payroll-run-refreshed')->assertSet('selected', []);

        $this->assertSame('36000.00', $this->rowOf($run, 'salma')->net->toDecimal());
        $this->assertEquals($karimBefore, $this->rowOf($run, 'karim')->calculated_at);   // untouched
        $this->assertSame('66000.00', $run->fresh()->total_net->toDecimal());
        $this->assertSame(PayrollRunStatus::Processed, $run->fresh()->status);

        // The breakdown has the same button.
        $this->salary($this->people['karim'], '31000');
        Livewire::test(PayrollRunEmployeeDetail::class)->call('show', $this->rowOf($run, 'karim')->id)
            ->assertSee('Calculate again')->call('recalculate')->assertDispatched('payroll-run-refreshed');
        $this->assertSame('67000.00', $run->fresh()->total_net->toDecimal());
    }

    public function test_a_draft_run_becomes_processed_once_nobody_is_pending(): void
    {
        $run = $this->makeRun();
        $action = app(RecalculatePayrollEmployees::class);

        $action->handle($run, [$this->rowOf($run, 'karim')->id]);
        $this->assertSame(PayrollRunStatus::Draft, $run->fresh()->status);
        $this->assertSame('30000.00', $run->fresh()->total_net->toDecimal());

        $action->handle($run->fresh(), [$this->rowOf($run, 'salma')->id]);
        $this->assertSame(PayrollRunStatus::Processed, $run->fresh()->status);
        $this->assertNotNull($run->fresh()->processed_at);
    }

    public function test_what_cannot_be_calculated_again(): void
    {
        $run = $this->makeRun();
        $action = app(RecalculatePayrollEmployees::class);
        $karim = $this->rowOf($run, 'karim')->id;

        $this->assertNull($action->problem($run, [$karim]));
        $this->assertStringContainsString('Tick the employees', $action->problem($run, []));
        $this->assertStringContainsString('not all in this run', $action->problem($run, [$karim, 999]));
        $this->assertStringContainsString('At most 50', $action->problem($run, range(1, 51)));

        $run->forceFill(['status' => PayrollRunStatus::Approved])->save();
        $this->assertStringContainsString('approved', $action->problem($run, [$karim]));
        Livewire::test(PayrollRegister::class, ['runId' => $run->id])->assertDontSee('Calculate again')
            ->set('selected', [(string) $karim])->call('recalculate')->assertNotDispatched('payroll-run-refreshed');
    }

    public function test_every_calculation_takes_the_bank_account_and_holds_as_they_are_now(): void
    {
        $run = $this->makeRun();
        $row = $this->rowOf($run, 'karim');
        $this->assertNull($row->bank_account_id);
        $this->assertFalse($row->is_on_hold);

        $account = $this->people['karim']->bankAccounts()->create(['bank_name' => 'City Bank', 'account_no' => '123', 'is_primary' => true]);
        $this->people['karim']->forceFill(['salary_on_hold' => true, 'salary_hold_reason' => 'Audit', 'separation_date' => '2026-12-20'])->save();

        app(RecalculatePayrollEmployees::class)->handle($run, [$row->id]);

        $row->refresh();
        $this->assertSame($account->id, $row->bank_account_id);
        $this->assertTrue($row->is_on_hold);
        $this->assertTrue($row->is_separated);
        $this->assertSame('20.00', $row->payable_days);
    }

    public function test_the_whole_run_takes_in_joiners_and_lets_go_of_people_out_of_scope(): void
    {
        $run = app(ProcessPayrollRun::class)->handle($this->makeRun());
        $adjustment = PayrollAdjustment::factory()->approved()->create(['employee_id' => $this->people['salma']->id]);
        app(ProcessPayrollRun::class)->handle($run->fresh());
        $this->assertSame($run->id, $adjustment->fresh()->payroll_run_id);

        $rafi = Employee::factory()->create(['code' => 'E-003', 'join_date' => '2026-12-10']);
        $this->salary($rafi, '31000');
        $this->people['salma']->forceFill(['separation_date' => '2026-11-25'])->save();   // left before December

        $action = app(ProcessPayrollRun::class);
        $action->handle($run->fresh());

        $this->assertSame(['added' => 1, 'removed' => 1], $action->scopeChange);
        $this->assertEqualsCanonicalizing([$this->people['karim']->id, $rafi->id], $run->employees()->pluck('employee_id')->all());
        $this->assertNull($adjustment->fresh()->payroll_run_id);                    // freed for a later run
        $this->assertSame(2, $run->fresh()->employee_count);
        $this->assertSame(PayrollCalcStatus::Ok, $run->employees()->where('employee_id', $rafi->id)->sole()->calc_status);

        // The page says so.
        Livewire::test(PayrollRunSummary::class, ['runId' => $run->id])->call('calculate')->assertDispatched('show-payroll-run');
    }

    public function test_a_run_made_for_a_list_of_codes_takes_in_nobody(): void
    {
        $run = $this->makeRun(['E-001']);
        $this->assertTrue($run->limited_to_list);
        Employee::factory()->create(['code' => 'E-003', 'join_date' => '2026-12-10']);

        $action = app(ProcessPayrollRun::class);
        $action->handle($run);

        $this->assertSame(['added' => 0, 'removed' => 0], $action->scopeChange);
        $this->assertSame([$this->people['karim']->id], $run->employees()->pluck('employee_id')->all());
    }
}
