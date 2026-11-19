<?php

namespace Tests\Feature\Payroll;

use App\Core\Support\Money;
use App\Core\Support\Period;
use App\Core\Support\PeriodCycle;
use App\Models\Tenant;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Payroll\Enums\HeadType;
use App\Modules\Payroll\Enums\PayrollCalcStatus;
use App\Modules\Payroll\Enums\PayrollItemSource;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Models\PayrollAdjustment;
use App\Modules\Payroll\Models\PayrollItem;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\PayrollRunEmployee;
use App\Modules\Payroll\Models\SalaryHead;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * 10.1: payroll run tables, models and the PayrollRunStatus lifecycle.
 */
class PayrollRunModelsTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->actingAsTenantUser()->tenant;   // Administrator
    }

    public function test_the_status_lifecycle(): void
    {
        $this->assertTrue(PayrollRunStatus::Draft->canMoveTo(PayrollRunStatus::Processing));
        $this->assertTrue(PayrollRunStatus::Processed->canMoveTo(PayrollRunStatus::Processing));   // re-run
        $this->assertTrue(PayrollRunStatus::Pending->canMoveTo(PayrollRunStatus::Processed));      // rejected
        $this->assertFalse(PayrollRunStatus::Draft->canMoveTo(PayrollRunStatus::Approved));
        $this->assertFalse(PayrollRunStatus::Locked->canMoveTo(PayrollRunStatus::Processed));
        $this->assertSame([], PayrollRunStatus::Disbursed->next());

        $this->assertTrue(PayrollRunStatus::Processed->isEditable());
        $this->assertFalse(PayrollRunStatus::Pending->isEditable());
        $this->assertTrue(PayrollRunStatus::Locked->isFinal());
    }

    public function test_a_run_keeps_its_period_and_moves_only_along_the_lifecycle(): void
    {
        $run = PayrollRun::factory()->make(['code' => 'PR-2027-02-1']);
        $run->setPeriod(Period::of(2027, 2, PeriodCycle::SecondHalf))->save();

        $run = $run->fresh();
        $this->assertSame(PayrollRunStatus::Draft, $run->status);
        $this->assertSame('2027-02-16', $run->period_start->toDateString());
        $this->assertSame('2027-02-28', $run->period_end->toDateString());
        $this->assertTrue($run->period()->equals(Period::of(2027, 2, PeriodCycle::SecondHalf)));
        $this->assertSame('February 2027 (2nd half) · Regular · Whole company', $run->title());

        $run->moveTo(PayrollRunStatus::Processing);
        $this->assertSame(PayrollRunStatus::Processing, $run->fresh()->status);

        $this->expectException(LogicException::class);
        $run->moveTo(PayrollRunStatus::Locked);
    }

    public function test_employees_items_and_totals(): void
    {
        $basic = SalaryHead::factory()->create(['is_basic' => true, 'amount' => null, 'sequence' => 1]);
        $tax = SalaryHead::factory()->create(['type' => HeadType::Deduction, 'sequence' => 9]);
        $run = PayrollRun::factory()->create();

        foreach (['30000', '20000'] as $i => $amount) {
            $row = $run->employees()->create([
                'employee_id' => Employee::factory()->create()->id,
                'period_days' => 31, 'payable_days' => 31,
                'basic' => $amount, 'gross' => $amount, 'deductions' => '1000', 'net' => Money::of($amount)->subtract(Money::of('1000')),
                'calc_status' => PayrollCalcStatus::Ok, 'warnings' => $i === 1 ? ['No bank account'] : null,
            ]);
            $row->items()->createMany([
                ['payroll_run_id' => $run->id, 'employee_id' => $row->employee_id, 'salary_head_id' => $basic->id, 'amount' => $amount, 'actual' => $amount],
                ['payroll_run_id' => $run->id, 'employee_id' => $row->employee_id, 'salary_head_id' => $tax->id, 'amount' => '1000', 'actual' => '1000', 'source' => PayrollItemSource::Tax],
            ]);
        }

        $run->refreshTotals();
        $run = $run->fresh();
        $this->assertSame(2, $run->employee_count);
        $this->assertSame('50000.00', $run->total_gross->toDecimal());
        $this->assertSame('2000.00', $run->total_deductions->toDecimal());
        $this->assertSame('48000.00', $run->total_net->toDecimal());
        $this->assertSame(4, $run->items()->count());

        $second = $run->employees()->latest('id')->first();
        $this->assertTrue($second->hasWarnings());
        $breakdown = $second->load('items.head')->breakdown();
        $this->assertSame('20000.00', $breakdown->gross->toDecimal());
        $this->assertSame('19000.00', $breakdown->net->toDecimal());
        $this->assertSame([$basic->id, $tax->id], array_keys($breakdown->amounts()));
    }

    public function test_an_employee_is_in_a_run_once(): void
    {
        $run = PayrollRun::factory()->create();
        $employee = Employee::factory()->create();
        $run->employees()->create(['employee_id' => $employee->id]);

        $this->expectException(QueryException::class);
        $run->employees()->create(['employee_id' => $employee->id]);
    }

    public function test_deleting_a_run_removes_its_rows_and_frees_its_adjustments(): void
    {
        $run = PayrollRun::factory()->create();
        $employee = Employee::factory()->create();
        $row = $run->employees()->create(['employee_id' => $employee->id]);
        $row->items()->create(['payroll_run_id' => $run->id, 'employee_id' => $employee->id, 'salary_head_id' => SalaryHead::factory()->create()->id, 'amount' => '100']);
        $adjustment = PayrollAdjustment::factory()->approved()->create(['employee_id' => $employee->id]);
        $adjustment->forceFill(['payroll_run_id' => $run->id])->save();
        $this->assertTrue($adjustment->isPaid());

        $run->delete();

        $this->assertSame(0, PayrollRunEmployee::count());
        $this->assertSame(0, PayrollItem::count());
        $this->assertFalse($adjustment->fresh()->isPaid());
    }

    public function test_an_employee_with_payroll_history_cannot_be_deleted(): void
    {
        $employee = Employee::factory()->create();
        PayrollRun::factory()->create()->employees()->create(['employee_id' => $employee->id]);

        $this->expectException(QueryException::class);
        $employee->delete();
    }

    public function test_due_adjustments_are_approved_unpaid_and_of_the_month(): void
    {
        $due = PayrollAdjustment::factory()->approved()->create();
        PayrollAdjustment::factory()->create();                                     // not approved
        PayrollAdjustment::factory()->approved()->create(['month' => 11]);          // other month
        $paid = PayrollAdjustment::factory()->approved()->create();
        $paid->forceFill(['payroll_run_id' => PayrollRun::factory()->create()->id])->save();

        $this->assertSame([$due->id], PayrollAdjustment::dueIn(Period::of(2026, 12))->pluck('id')->all());
    }

    public function test_period_scopes_and_visibility(): void
    {
        $sales = Entity::factory()->create();
        $finance = Entity::factory()->create();
        $december = PayrollRun::factory()->create(['entity_id' => $sales->id]);
        $november = PayrollRun::factory()->forPeriod(Period::of(2026, 11))->create(['entity_id' => $finance->id]);
        $company = PayrollRun::factory()->create();

        $this->assertSame([$november->id], PayrollRun::forPeriod(Period::of(2026, 12)->previous())->regular()->pluck('id')->all());

        $admin = auth()->user();
        $this->assertCount(3, PayrollRun::visibleTo($admin)->get());

        $user = $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['payroll.salary.view']);
        Role::where('name', 'like', 'Test role%')->latest('id')->firstOrFail()->update(['entity_id' => $sales->id]);
        $this->assertSame([$december->id], PayrollRun::visibleTo($user)->pluck('id')->all());
        $this->assertNotContains($company->id, PayrollRun::visibleTo($user)->pluck('id')->all());
    }
}
