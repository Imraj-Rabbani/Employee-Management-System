<?php

namespace Tests\Feature\Payroll;

use App\Core\Support\Money;
use App\Models\Tenant;
use App\Modules\Hrm\Contracts\SalaryBenchmarks;
use App\Modules\Hrm\Enums\EmployeeStatus;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Hrm\Models\JobOffer;
use App\Modules\Payroll\Actions\SetEmployeeSalary;
use App\Modules\Payroll\Data\SalaryBreakdown;
use App\Modules\Payroll\Data\SalaryLine;
use App\Modules\Payroll\Enums\HeadType;
use App\Modules\Payroll\Enums\ProfileValueType;
use App\Modules\Payroll\Livewire\EmployeeSalaryTab;
use App\Modules\Payroll\Models\EmployeeSalary;
use App\Modules\Payroll\Models\SalaryHead;
use App\Modules\Payroll\Models\SalaryProfile;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Effective-dated employee salaries, the profile's Salary tab, hold flags, and comparable salaries on a
 * job offer.
 */
class EmployeeSalaryTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private SalaryHead $basic;

    private SalaryHead $houseRent;

    private SalaryHead $pf;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->actingAsTenantUser()->tenant;

        $this->basic = SalaryHead::factory()->create(['code' => 'BASIC', 'name' => 'Basic', 'is_basic' => true, 'amount' => null, 'sequence' => 1]);
        $this->houseRent = SalaryHead::factory()->percentOfBasic('50')->create(['code' => 'HR', 'name' => 'House rent', 'sequence' => 2]);
        $this->pf = SalaryHead::factory()->create(['code' => 'PF', 'name' => 'Provident fund', 'type' => HeadType::Deduction, 'in_gross' => false, 'sequence' => 9]);
        $this->employee = Employee::factory()->create(['join_date' => '2026-01-01']);
    }

    /** @param  array<int, string>  $amounts  head id => amount */
    private function salary(array $amounts): SalaryBreakdown
    {
        $heads = SalaryHead::findMany(array_keys($amounts))->keyBy('id');

        return SalaryBreakdown::fromLines(array_map(
            fn (int $id) => new SalaryLine($heads[$id], '', Money::of($amounts[$id])),
            array_keys($amounts),
        ));
    }

    private function set(string $from, array $amounts, ?Employee $employee = null): void
    {
        app(SetEmployeeSalary::class)->handle($employee ?? $this->employee, CarbonImmutable::parse($from), $this->salary($amounts));
    }

    public function test_a_change_closes_the_old_salary_the_day_before(): void
    {
        $this->set('2026-01-01', [$this->basic->id => '20000', $this->houseRent->id => '10000']);
        $this->set('2026-07-01', [$this->basic->id => '24000', $this->houseRent->id => '12000', $this->pf->id => '0']);

        $old = EmployeeSalary::where('effective_from', '2026-01-01')->get();
        $this->assertCount(2, $old);
        $this->assertSame(['2026-06-30'], $old->map(fn ($r) => $r->effective_to->toDateString())->unique()->values()->all());

        $new = EmployeeSalary::where('employee_id', $this->employee->id)->effectiveOn('2026-07-15')->with('head')->get();
        $this->assertCount(2, $new);   // PF 0 is left out
        $this->assertSame('36000.00', EmployeeSalary::breakdown($new)->gross->toDecimal());
        $this->assertSame('30000.00', EmployeeSalary::breakdown(EmployeeSalary::effectiveOn('2026-06-30')->with('head')->get())->gross->toDecimal());

        $this->assertCount(2, EmployeeSalary::historyOf($this->employee));
        $this->assertDatabaseHas('activity_log', ['subject_id' => $this->employee->id, 'event' => 'updated'], 'tenant');
    }

    public function test_the_same_start_date_corrects_the_salary(): void
    {
        $this->set('2026-01-01', [$this->basic->id => '20000']);
        $this->set('2026-07-01', [$this->basic->id => '24000']);
        $this->set('2026-07-01', [$this->basic->id => '25000', $this->houseRent->id => '12500']);

        $this->assertSame(['25000.00', '12500.00'], EmployeeSalary::where('effective_from', '2026-07-01')->orderBy('salary_head_id')->get()->map(fn ($r) => $r->amount->toDecimal())->all());
        $this->assertSame('2026-06-30', EmployeeSalary::where('effective_from', '2026-01-01')->sole()->effective_to->toDateString());
    }

    public function test_problems(): void
    {
        $action = app(SetEmployeeSalary::class);
        $this->set('2026-07-01', [$this->basic->id => '24000']);

        $this->assertStringContainsString('already starts', $action->problem($this->employee, CarbonImmutable::parse('2026-03-01'), $this->salary([$this->basic->id => '1']))['message']);
        $this->assertStringContainsString('joining date', $action->problem($this->employee, CarbonImmutable::parse('2025-12-31'), $this->salary([$this->basic->id => '1']))['message']);
        $this->assertSame('lines', $action->problem($this->employee, CarbonImmutable::parse('2026-08-01'), $this->salary([$this->houseRent->id => '100']))['field']);   // no basic
        $this->assertNull($action->problem($this->employee, CarbonImmutable::parse('2026-08-01'), $this->salary([$this->basic->id => '1'])));
    }

    public function test_the_tab_applies_a_profile_and_saves(): void
    {
        $profile = SalaryProfile::factory()->create(['name' => 'Grade 5']);
        $profile->syncItems([
            $this->houseRent->id => ['value_type' => 'percent_basic', 'value' => '50'],
            $this->pf->id => ['value_type' => 'percent_basic', 'value' => '10'],
        ]);
        $this->employee->update(['salary_profile_id' => $profile->id]);

        Livewire::test(EmployeeSalaryTab::class, ['employee' => $this->employee])
            ->call('openSalary')
            ->assertSet('form.effective_from', '2026-01-01')          // first salary: from the joining date
            ->assertSet('form.salary_profile_id', (string) $profile->id)
            ->set('form.basis', 'gross')->set('form.amount', '30000')
            ->call('applyProfile')
            ->assertSet("lines.{$this->basic->id}", '20000.00')
            ->assertSet("lines.{$this->houseRent->id}", '10000.00')
            ->assertSet("lines.{$this->pf->id}", '2000.00')
            ->set("lines.{$this->pf->id}", '1500')                   // typed over: a plain amount now
            ->call('saveSalary')
            ->assertHasNoErrors();

        $rows = EmployeeSalary::where('employee_id', $this->employee->id)->get()->keyBy('salary_head_id');
        $this->assertSame(ProfileValueType::PercentOfBasic, $rows[$this->houseRent->id]->value_type);
        $this->assertSame('50.0000', $rows[$this->houseRent->id]->rate);
        $this->assertNull($rows[$this->pf->id]->value_type);
        $this->assertSame('1500.00', $rows[$this->pf->id]->amount->toDecimal());
        $this->assertSame($profile->id, $rows[$this->basic->id]->salary_profile_id);

        Livewire::test(EmployeeSalaryTab::class, ['employee' => $this->employee])
            ->assertSee('Current salary')->assertSeeHtml('30,000.00')->assertSeeHtml('28,500.00')   // gross, net
            ->assertSee('50% of basic');
    }

    public function test_the_tab_refuses_a_salary_before_the_latest(): void
    {
        $this->set('2026-07-01', [$this->basic->id => '24000']);

        Livewire::test(EmployeeSalaryTab::class, ['employee' => $this->employee])
            ->call('openSalary')
            ->assertSet("lines.{$this->basic->id}", '24000.00')   // starts from the current salary
            ->set('form.effective_from', '2026-03-01')
            ->call('saveSalary')
            ->assertHasErrors('form.effective_from');
    }

    public function test_hold_needs_a_reason_and_clearing_drops_it(): void
    {
        $tab = Livewire::test(EmployeeSalaryTab::class, ['employee' => $this->employee])
            ->call('openHold')
            ->set('hold.salary_on_hold', true)
            ->call('saveHold')->assertHasErrors('hold.salary_hold_reason')
            ->set('hold.salary_hold_reason', 'Absconding')
            ->call('saveHold')->assertHasNoErrors()
            ->assertSee('Salary on hold')->assertSee('Absconding');

        $this->assertTrue($this->employee->fresh()->salary_on_hold);

        $tab->call('openHold')->set('hold.salary_on_hold', false)->call('saveHold');
        $fresh = $this->employee->fresh();
        $this->assertFalse($fresh->salary_on_hold);
        $this->assertNull($fresh->salary_hold_reason);
    }

    public function test_the_tab_needs_salary_permissions(): void
    {
        $this->get(route('hrm.employees.show', $this->employee).'?tab=salary')->assertOk()->assertSee('No salary yet');

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.employee.view']);
        $this->get(route('hrm.employees.show', $this->employee).'?tab=salary')->assertOk()->assertDontSee('No salary yet');   // no tab: falls back

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.employee.view', 'payroll.salary.view']);
        Livewire::test(EmployeeSalaryTab::class, ['employee' => $this->employee])
            ->assertSee('No salary yet')->assertDontSee('Set salary')
            ->call('openSalary')->assertForbidden();
    }

    public function test_comparable_salaries_need_three_people(): void
    {
        $grade = Grade::factory()->create();
        foreach (['20000', '30000'] as $basic) {
            $this->set('2026-01-01', [$this->basic->id => $basic, $this->pf->id => '999'], Employee::factory()->create(['join_date' => '2026-01-01', 'grade_id' => $grade->id]));
        }
        $gone = Employee::factory()->create(['join_date' => '2026-01-01', 'grade_id' => $grade->id]);
        $this->set('2026-01-01', [$this->basic->id => '90000'], $gone);
        $gone->update(['status' => EmployeeStatus::Separated]);

        $benchmarks = app(SalaryBenchmarks::class);
        $this->assertSame(['count' => 2, 'min' => null, 'max' => null, 'average' => null], $benchmarks->grossRange('grade_id', $grade->id));

        $this->set('2026-01-01', [$this->basic->id => '40000', $this->houseRent->id => '5000'], Employee::factory()->create(['join_date' => '2026-01-01', 'grade_id' => $grade->id]));
        // Gross only (PF is a deduction), separated left out.
        $this->assertSame(['count' => 3, 'min' => '20000.00', 'max' => '45000.00', 'average' => '31666.67'], $benchmarks->grossRange('grade_id', $grade->id));

        // On the offer: a row with the range and a verdict.
        $offer = new JobOffer(['gross_salary' => '50000', 'grade_id' => $grade->id]);
        $row = collect($offer->comparisons())->firstWhere('label', 'Same grade now (3 employees)');
        $this->assertSame('20,000 – 45,000 (average 31,667)', $row['range']);
        $this->assertSame('above', $row['verdict']);
    }
}
