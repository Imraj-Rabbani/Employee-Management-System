<?php

namespace Tests\Feature\Payroll;

use App\Core\Support\Period;
use App\Core\Support\PeriodCycle;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Payroll\Actions\CreatePayrollRun;
use App\Modules\Payroll\Data\NewPayrollRun;
use App\Modules\Payroll\Enums\HeadType;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Enums\PayrollRunType;
use App\Modules\Payroll\Livewire\PayrollRunForm;
use App\Modules\Payroll\Livewire\PayrollRunTable;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\SalaryHead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * 10.2: CreatePayrollRun (scope, duplicate-period guard), the run list and the New / Edit run form.
 */
class CreatePayrollRunTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->actingAsTenantUser();   // Administrator
        $this->tenant = $this->admin->tenant;
    }

    private function make(NewPayrollRun $input): PayrollRun
    {
        return app(CreatePayrollRun::class)->handle($input, $this->admin);
    }

    /** @return array{field: string, message: string}|null */
    private function problem(NewPayrollRun $input, ?User $user = null): ?array
    {
        return app(CreatePayrollRun::class)->problem($input, $user ?? $this->admin);
    }

    public function test_a_regular_run_lists_everyone_employed_in_the_period(): void
    {
        $in = Employee::factory()->create(['code' => 'E1', 'join_date' => '2025-01-01', 'salary_on_hold' => true, 'salary_hold_reason' => 'Audit']);
        $leaver = Employee::factory()->create(['code' => 'E2', 'join_date' => '2025-01-01', 'separation_date' => '2026-12-10']);
        Employee::factory()->create(['code' => 'E3', 'join_date' => '2027-01-05']);                                  // joins later
        Employee::factory()->create(['code' => 'E4', 'join_date' => '2025-01-01', 'separation_date' => '2026-11-30']);  // left before
        $in->bankAccounts()->create(['bank_name' => 'City Bank', 'account_name' => 'X', 'account_no' => '1', 'is_primary' => true]);

        $run = $this->make(new NewPayrollRun(Period::of(2026, 12), payDate: now()->setDate(2026, 12, 31)->toImmutable()));

        $this->assertSame('PR-2026-12-01', $run->code);
        $this->assertSame(PayrollRunStatus::Draft, $run->status);
        $this->assertSame('2026-12-01', $run->period_start->toDateString());
        $this->assertSame(2, $run->employee_count);
        $this->assertSame($this->admin->id, $run->created_by_user_id);

        $rows = $run->employees()->get()->keyBy('employee_id');
        $this->assertEqualsCanonicalizing([$in->id, $leaver->id], $rows->keys()->all());
        $this->assertTrue($rows[$in->id]->is_on_hold);
        $this->assertNotNull($rows[$in->id]->bank_account_id);
        $this->assertSame('31.00', $rows[$in->id]->period_days);
        $this->assertTrue($rows[$leaver->id]->is_separated);
        $this->assertFalse($rows[$in->id]->is_separated);
    }

    public function test_only_one_regular_run_per_period_and_scope(): void
    {
        $sales = Entity::factory()->create(['name' => 'Sales']);
        $finance = Entity::factory()->create(['name' => 'Finance']);
        Employee::factory()->inEntity($sales)->create(['join_date' => '2025-01-01']);
        Employee::factory()->inEntity($finance)->create(['join_date' => '2025-01-01']);

        $this->make(new NewPayrollRun(Period::of(2026, 12), entityId: $sales->id));

        // Same entity again, the whole company, or a half-month run inside the same month: refused.
        $this->assertSame('period', $this->problem(new NewPayrollRun(Period::of(2026, 12), entityId: $sales->id))['field']);
        $this->assertStringContainsString('PR-2026-12-01', $this->problem(new NewPayrollRun(Period::of(2026, 12)))['message']);
        $this->assertNotNull($this->problem(new NewPayrollRun(Period::of(2026, 12, PeriodCycle::SecondHalf), entityId: $sales->id)));

        // Another entity, another month, or an off-cycle run: fine.
        $this->assertNull($this->problem(new NewPayrollRun(Period::of(2026, 12), entityId: $finance->id)));
        $this->assertNull($this->problem(new NewPayrollRun(Period::of(2027, 1), entityId: $sales->id)));
        $this->assertNull($this->problem(new NewPayrollRun(Period::of(2026, 12), PayrollRunType::OffCycle, entityId: $sales->id)));
        $this->assertSame('PO-2026-12-01', $this->make(new NewPayrollRun(Period::of(2026, 12), PayrollRunType::OffCycle, entityId: $sales->id))->code);
    }

    public function test_people_in_two_entities_are_not_paid_twice(): void
    {
        $sales = Entity::factory()->create();
        $dhaka = Entity::factory()->create();                     // e.g. a branch: shares people with Sales
        Employee::factory()->inEntity($sales, $dhaka)->create(['join_date' => '2025-01-01']);

        $this->make(new NewPayrollRun(Period::of(2026, 12), entityId: $sales->id));

        $problem = $this->problem(new NewPayrollRun(Period::of(2026, 12), entityId: $dhaka->id));
        $this->assertSame('entity_id', $problem['field']);
        $this->assertStringContainsString('1 employee of this scope is already in PR-2026-12-01', $problem['message']);
    }

    public function test_head_wise_runs_need_an_earning_head_and_are_guarded_per_head(): void
    {
        Employee::factory()->create(['join_date' => '2025-01-01']);
        $bonus = SalaryHead::factory()->create();
        $other = SalaryHead::factory()->create();
        $pf = SalaryHead::factory()->create(['type' => HeadType::Deduction]);

        $this->assertSame('salary_head_id', $this->problem(new NewPayrollRun(Period::of(2026, 12), PayrollRunType::HeadWise))['field']);
        $this->assertSame('salary_head_id', $this->problem(new NewPayrollRun(Period::of(2026, 12), PayrollRunType::HeadWise, salaryHeadId: $pf->id))['field']);

        $run = $this->make(new NewPayrollRun(Period::of(2026, 12), PayrollRunType::HeadWise, salaryHeadId: $bonus->id));
        $this->assertSame('PH-2026-12-01', $run->code);
        $this->assertSame($bonus->id, $run->salary_head_id);

        $this->assertNotNull($this->problem(new NewPayrollRun(Period::of(2026, 12), PayrollRunType::HeadWise, salaryHeadId: $bonus->id)));
        $this->assertNull($this->problem(new NewPayrollRun(Period::of(2026, 12), PayrollRunType::HeadWise, salaryHeadId: $other->id)));
        $this->assertNull($this->problem(new NewPayrollRun(Period::of(2026, 12))));   // the regular run is separate
    }

    public function test_an_employee_list_narrows_the_scope(): void
    {
        Employee::factory()->create(['code' => 'A1', 'join_date' => '2025-01-01']);
        Employee::factory()->create(['code' => 'A2', 'join_date' => '2025-01-01']);
        Employee::factory()->create(['code' => 'A3', 'join_date' => '2025-01-01']);

        $problem = $this->problem(new NewPayrollRun(Period::of(2026, 12), PayrollRunType::OffCycle, employeeCodes: ['A1', 'ZZ9']));
        $this->assertSame('employee_codes', $problem['field']);
        $this->assertStringContainsString('ZZ9', $problem['message']);

        $run = $this->make(new NewPayrollRun(Period::of(2026, 12), PayrollRunType::OffCycle, employeeCodes: ['A1', 'A3']));
        $this->assertSame(2, $run->employee_count);
        $this->assertSame(['A1', 'A3'], $run->employees()->with('employee')->get()->pluck('employee.code')->sort()->values()->all());
    }

    public function test_an_empty_scope_or_a_pay_date_before_the_period_is_refused(): void
    {
        $this->assertSame('entity_id', $this->problem(new NewPayrollRun(Period::of(2026, 12)))['field']);   // nobody employed

        Employee::factory()->create(['join_date' => '2025-01-01']);
        $this->assertSame('pay_date', $this->problem(new NewPayrollRun(Period::of(2026, 12), payDate: now()->setDate(2026, 11, 30)->toImmutable()))['field']);
    }

    public function test_an_entity_limited_user_must_run_their_own_entity(): void
    {
        $sales = Entity::factory()->create();
        $finance = Entity::factory()->create();
        Employee::factory()->inEntity($sales)->create(['join_date' => '2025-01-01']);
        Employee::factory()->inEntity($finance)->create(['join_date' => '2025-01-01']);

        $user = $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['payroll.run.view', 'payroll.run.process']);
        Role::where('name', 'like', 'Test role%')->latest('id')->firstOrFail()->update(['entity_id' => $sales->id]);

        $this->assertSame('entity_id', $this->problem(new NewPayrollRun(Period::of(2026, 12)), $user)['field']);
        $this->assertSame('entity_id', $this->problem(new NewPayrollRun(Period::of(2026, 12), entityId: $finance->id), $user)['field']);
        $this->assertNull($this->problem(new NewPayrollRun(Period::of(2026, 12), entityId: $sales->id), $user));
    }

    public function test_the_form_creates_edits_and_deletes_a_run(): void
    {
        Employee::factory()->count(2)->create(['join_date' => '2025-01-01']);

        Livewire::test(PayrollRunForm::class)
            ->dispatch('create-payroll-run')
            ->set('form.year', '2026')->set('form.month', '12')
            ->set('form.pay_date', '2026-12-31')
            ->call('save')->assertHasNoErrors()
            ->assertDispatched('payroll-run-saved');

        $run = PayrollRun::sole();
        $this->assertSame(2, $run->employee_count);
        $this->assertSame('2026-12-31', $run->pay_date->toDateString());

        // The same period again: the guard's message under the period.
        Livewire::test(PayrollRunForm::class)
            ->dispatch('create-payroll-run')
            ->set('form.year', '2026')->set('form.month', '12')
            ->call('save')->assertHasErrors('form.period');

        Livewire::test(PayrollRunForm::class)
            ->dispatch('edit-payroll-run', id: $run->id)
            ->set('form.note', 'Includes December bonus')
            ->call('save')->assertHasNoErrors();
        $this->assertSame('Includes December bonus', $run->fresh()->note);

        Livewire::test(PayrollRunTable::class)->assertSee($run->code)->assertSee('December 2026');

        Livewire::test(PayrollRunForm::class)->dispatch('delete-payroll-run', ids: [$run->id]);
        $this->assertSame(0, PayrollRun::count());
    }

    public function test_a_submitted_run_cannot_be_edited_or_deleted(): void
    {
        $run = PayrollRun::factory()->create();
        $run->forceFill(['status' => PayrollRunStatus::Pending])->save();

        Livewire::test(PayrollRunForm::class)
            ->dispatch('edit-payroll-run', id: $run->id)
            ->set('form.note', 'x')
            ->call('save')->assertHasErrors('form.note');

        Livewire::test(PayrollRunForm::class)->dispatch('delete-payroll-run', ids: [$run->id]);
        $this->assertSame(1, PayrollRun::count());
    }

    public function test_the_page_needs_the_view_permission(): void
    {
        $this->get(route('payroll.runs'))->assertOk()->assertSee('Payroll runs');

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['payroll.salary.view']);
        $this->get(route('payroll.runs'))->assertForbidden();
    }
}
