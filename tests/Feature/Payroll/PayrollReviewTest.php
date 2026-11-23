<?php

namespace Tests\Feature\Payroll;

use App\Core\Support\Period;
use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Actions\CalculateEmployeePayroll;
use App\Modules\Payroll\Actions\CreatePayrollRun;
use App\Modules\Payroll\Data\NewPayrollRun;
use App\Modules\Payroll\Enums\HeadType;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Livewire\PayrollExceptions;
use App\Modules\Payroll\Livewire\PayrollRegister;
use App\Modules\Payroll\Livewire\PayrollRunEmployeeDetail;
use App\Modules\Payroll\Livewire\PayrollRunSummary;
use App\Modules\Payroll\Models\EmployeeSalary;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\PayrollRunEmployee;
use App\Modules\Payroll\Models\SalaryHead;
use App\Modules\Payroll\Services\PayrollReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * 10.8: the run's review page — register, variance against the previous period, exceptions, one employee's breakdown.
 *
 *   Karim   E-001  30,000 + 15,000 rent − 1,500 PF = 43,500 in November and December
 *   Salma   E-002  20,000 → 25,000 from December: 28,500 → 36,000 (+26.3 %)
 *   Rafi    E-003  joined 1 December: not in November
 *   Nabil   E-004  joined 1 December, no salary: failed
 */
class PayrollReviewTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private User $admin;

    /** @var array<string, SalaryHead> */
    private array $heads;

    /** @var array<string, Employee> */
    private array $people;

    private PayrollRun $november;

    private PayrollRun $december;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->actingAsTenantUser();
        $this->heads = [
            'basic' => SalaryHead::factory()->create(['code' => 'BASIC', 'is_basic' => true, 'amount' => null, 'sequence' => 1]),
            'rent' => SalaryHead::factory()->create(['code' => 'RENT', 'name' => 'House rent', 'sequence' => 2]),
            'pf' => SalaryHead::factory()->create(['code' => 'PF', 'type' => HeadType::Deduction, 'sequence' => 3]),
        ];

        $this->people = [
            'karim' => Employee::factory()->create(['code' => 'E-001', 'first_name' => 'Karim', 'join_date' => '2025-01-01']),
            'salma' => Employee::factory()->create(['code' => 'E-002', 'first_name' => 'Salma', 'join_date' => '2025-01-01']),
            'rafi' => Employee::factory()->create(['code' => 'E-003', 'first_name' => 'Rafi', 'join_date' => '2026-12-01']),
            'nabil' => Employee::factory()->create(['code' => 'E-004', 'first_name' => 'Nabil', 'join_date' => '2026-12-01']),
        ];
        $this->salary('karim', '2025-01-01', '30000');
        $this->salary('salma', '2025-01-01', '20000', '2026-11-30');
        $this->salary('salma', '2026-12-01', '25000');
        $this->salary('rafi', '2026-12-01', '30000');

        $this->november = $this->calculatedRun(11, ['E-001', 'E-002']);
        $this->december = $this->calculatedRun(12, ['E-001', 'E-002', 'E-003', 'E-004']);
    }

    private function salary(string $who, string $from, string $basic, ?string $to = null): void
    {
        foreach (['basic' => $basic, 'rent' => bcdiv($basic, '2', 2), 'pf' => '1500'] as $head => $amount) {
            EmployeeSalary::create(['employee_id' => $this->people[$who]->id, 'salary_head_id' => $this->heads[$head]->id, 'amount' => $amount, 'effective_from' => $from, 'effective_to' => $to]);
        }
    }

    /** @param  list<string>  $codes */
    private function calculatedRun(int $month, array $codes): PayrollRun
    {
        $run = app(CreatePayrollRun::class)->handle(new NewPayrollRun(Period::of(2026, $month), employeeCodes: $codes), $this->admin);
        foreach ($run->employees as $row) {
            app(CalculateEmployeePayroll::class)->handle($row);
        }
        $run->refreshTotals();
        $run->forceFill(['status' => PayrollRunStatus::Processed, 'processed_at' => now()])->save();

        return $run->fresh();
    }

    private function rowOf(string $who): PayrollRunEmployee
    {
        return $this->december->employees()->where('employee_id', $this->people[$who]->id)->sole();
    }

    public function test_the_page_shows_the_run_with_its_totals_against_the_previous_period(): void
    {
        $this->get(route('payroll.runs'))->assertOk()->assertSee(route('payroll.runs.show', $this->december->id));

        $this->get(route('payroll.runs.show', $this->december->id))->assertOk()
            ->assertSee($this->december->code)->assertSee('Register')->assertSee('Exceptions');

        // November 72,000 → December 43,500 + 36,000 + 43,500 = 123,000 (+70.8 %); 1 failed.
        Livewire::test(PayrollRunSummary::class, ['runId' => $this->december->id])
            ->assertSee('123,000.00')->assertSee('+51,000.00 · +70.8 %')->assertSee('vs '.$this->november->code)->assertSee('1 failed');

        $this->get(route('payroll.runs.show', 999))->assertNotFound();
        $this->actingAsTenantUser(tenant: $this->admin->tenant, permissions: ['payroll.adjustment.view']);
        $this->get(route('payroll.runs.show', $this->december->id))->assertForbidden();
    }

    public function test_the_register_has_a_column_per_head_and_the_change_per_employee(): void
    {
        Livewire::test(PayrollRegister::class, ['runId' => $this->december->id])
            ->assertSeeInOrder(['BASIC', 'RENT', 'PF', 'Gross', 'Deductions', 'Net'])
            ->assertSeeInOrder(['E-001', 'E-002', 'E-003', 'E-004'])
            ->assertSee('25,000.00')->assertSee('28,500.00')->assertSee('+26.3 %')   // Salma
            ->assertSee('new')                                                       // Rafi
            ->assertSee('No salary on')                                              // Nabil failed
            ->assertSee('123,000.00');                                               // total net
    }

    public function test_the_register_filters_and_sorts(): void
    {
        $register = fn (array $set) => tap(Livewire::test(PayrollRegister::class, ['runId' => $this->december->id]), function ($c) use ($set) {
            foreach ($set as $k => $v) {
                $c->set($k, $v);
            }
            $c->call('applyFilters');
        });

        $register(['status' => 'variance'])->assertSee('E-002')->assertDontSee('E-001')->assertDontSee('E-003');
        $register(['status' => 'new'])->assertSee('E-003')->assertSee('E-004')->assertDontSee('E-002');
        $register(['status' => 'failed'])->assertSee('E-004')->assertDontSee('E-003');
        $register(['search' => 'salma'])->assertSee('E-002')->assertDontSee('E-001')->assertSee('36,000.00');

        Livewire::test(PayrollRegister::class, ['runId' => $this->december->id])
            ->call('sort', 'net')->call('sort', 'net')   // descending
            ->assertSeeInOrder(['E-001', 'E-002', 'E-004']);

        Livewire::test(PayrollRegister::class, ['runId' => $this->december->id])->call('export')
            ->assertFileDownloaded('payroll-register-'.$this->december->code.'.xlsx');
    }

    public function test_the_exception_list_groups_what_to_check(): void
    {
        $groups = app(PayrollReview::class)->exceptions($this->december);

        $this->assertSame(['failed', 'variance', 'new', 'bank'], array_keys($groups));
        $this->assertSame([$this->people['nabil']->id], array_map(fn ($i) => $i['row']->employee_id, $groups['failed']['items']));
        $this->assertSame([$this->people['salma']->id], array_map(fn ($i) => $i['row']->employee_id, $groups['variance']['items']));
        $this->assertSame([$this->people['rafi']->id], array_map(fn ($i) => $i['row']->employee_id, $groups['new']['items']));

        Livewire::test(PayrollExceptions::class, ['runId' => $this->december->id])
            ->assertSee('Failed')->assertSee('Net pay changed by 10 % or more')
            ->assertSee('28,500.00 ('.$this->november->code.') → 36,000.00 (+26.3 %)')
            ->assertSee('No bank account');
    }

    public function test_one_employee_in_full(): void
    {
        $salma = $this->rowOf('salma');

        Livewire::test(PayrollRunEmployeeDetail::class)->call('show', $salma->id)
            ->assertSee('E-002')->assertSee('Earnings')->assertSee('Deductions')
            ->assertSeeInOrder(['House rent', '12,500.00', '10,000.00'])   // now, and November
            ->assertSee('36,000.00')->assertSee('+26.3 %');

        Livewire::test(PayrollRunEmployeeDetail::class)->call('show', 999)->assertNotFound();
    }

    public function test_calculate_again_from_the_page_refreshes_the_register(): void
    {
        Livewire::test(PayrollRunSummary::class, ['runId' => $this->december->id])
            ->call('calculate')
            ->assertDispatched('show-payroll-run')
            ->assertDispatched('payroll-run-refreshed');   // the test queue is sync: already done

        $this->assertSame(PayrollRunStatus::Processed, $this->december->fresh()->status);
    }
}
