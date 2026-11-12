<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Modules\Hrm\Data\LeaveYear;
use App\Modules\Hrm\Enums\LeaveEntryType;
use App\Modules\Hrm\Livewire\Leave\LeaveBalanceTable;
use App\Modules\Hrm\Livewire\Leave\LeaveCalendar;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveApplication;
use App\Modules\Hrm\Models\LeaveType;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Services\LeaveLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 8.7: HRM → Leave → Calendar (who is on leave) and Balances (the balance report). [LV-03, LV-04]
 */
class LeaveReportsTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private LeaveType $casual;

    private Employee $karim;

    private Employee $rina;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-10 12:00');
        $this->tenant = $this->createMigratedTenant();
        $this->actingAsTenantUser(tenant: $this->tenant);

        $this->casual = LeaveType::factory()->create(['code' => 'CL', 'name' => 'Casual leave']);
        $this->karim = Employee::factory()->create(['code' => 'E001', 'first_name' => 'Karim', 'org_unit_id' => OrgUnit::factory()->create(['name' => 'Finance'])->id]);
        $this->rina = Employee::factory()->create(['code' => 'E002', 'first_name' => 'Rina', 'org_unit_id' => OrgUnit::factory()->create(['name' => 'Sales'])->id]);
    }

    /** @param  array<string, float>  $days  'Y-m-d' => 1 or 0.5 */
    private function leave(Employee $employee, array $days, bool $approved = true, bool $withdrawn = false): LeaveApplication
    {
        $application = LeaveApplication::create([
            'employee_id' => $employee->id, 'leave_type_id' => $this->casual->id,
            'from_date' => array_key_first($days), 'to_date' => array_key_last($days),
            'days' => array_sum($days), 'leave_year' => 2026,
        ]);
        $application->forceFill(['approved_at' => $approved ? now() : null, 'withdrawn_at' => $withdrawn ? now() : null])->save();
        foreach ($days as $date => $value) {
            $application->leaveDays()->create(['employee_id' => $employee->id, 'date' => $date, 'days' => $value]);
        }

        return $application;
    }

    public function test_the_calendar_shows_who_is_on_leave(): void
    {
        $this->leave($this->karim, ['2026-12-14' => 1, '2026-12-15' => 0.5]);
        $this->leave($this->rina, ['2026-12-15' => 1], approved: false);              // waiting: shown too
        $this->leave($this->rina, ['2026-12-21' => 1], withdrawn: true);              // withdrawn: not shown

        $this->get(route('hrm.leave.calendar'))->assertOk()->assertSee('December 2026');

        $calendar = Livewire::test(LeaveCalendar::class)
            ->assertSee('E001 · Karim')->assertSee('E002 · Rina')
            ->assertSee('CL½')
            ->assertSee('Waiting for approval');
        $this->assertSame(['2026-12-14' => 1.0, '2026-12-15' => 1.5], $calendar->viewData('perDay'));

        // Org unit filter, and another month.
        Livewire::test(LeaveCalendar::class)->set('orgUnitId', (string) $this->karim->org_unit_id)->assertSee('E001 · Karim')->assertDontSee('E002 · Rina');
        Livewire::test(LeaveCalendar::class)->call('next')->assertSet('month', '2027-01')->assertSee('Nobody is on leave in January 2027');
    }

    public function test_the_balance_report_lists_a_leave_years_balances(): void
    {
        $ledger = app(LeaveLedger::class);
        $ledger->credit($this->karim, $this->casual, LeaveYear::of(2026), 10, LeaveEntryType::Earned);
        $ledger->debit($this->karim, $this->casual, LeaveYear::of(2026), 2.5, LeaveEntryType::Taken);
        $ledger->credit($this->rina, $this->casual, LeaveYear::of(2025), 4, LeaveEntryType::Opening);   // another year

        $this->get(route('hrm.leave.balances'))->assertOk();

        Livewire::test(LeaveBalanceTable::class)
            ->assertSet('filters.year', '2026')        // this leave year by default
            ->assertSee('E001 · Karim')->assertSee('7.5')->assertSee('2.5')
            ->assertDontSee('E002 · Rina')
            ->set('filters.year', '2025')
            ->assertSee('E002 · Rina')->assertDontSee('E001 · Karim');
    }

    public function test_both_need_the_permission(): void
    {
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.leave.setup']);

        $this->get(route('hrm.leave.calendar'))->assertForbidden();
        $this->get(route('hrm.leave.balances'))->assertForbidden();
        $this->get(route('hrm.leave.encashments'))->assertForbidden();
    }
}
