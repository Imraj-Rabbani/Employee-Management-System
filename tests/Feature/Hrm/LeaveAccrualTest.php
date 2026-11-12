<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Modules\Hrm\Actions\ApplyForLeave;
use App\Modules\Hrm\Data\LeaveYear;
use App\Modules\Hrm\Enums\LeaveEarnType;
use App\Modules\Hrm\Enums\LeaveEntryType;
use App\Modules\Hrm\Livewire\Leave\LeaveSettings;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveAccrual;
use App\Modules\Hrm\Models\LeaveApplication;
use App\Modules\Hrm\Models\LeaveBalance;
use App\Modules\Hrm\Models\LeaveLedgerEntry;
use App\Modules\Hrm\Models\LeaveProfile;
use App\Modules\Hrm\Models\LeaveType;
use App\Modules\Hrm\Services\AccrueEarnedLeave;
use App\Modules\Hrm\Services\CarryForwardLeave;
use App\Modules\Hrm\Services\LeaveLedger;
use App\Modules\Payroll\Enums\AttendanceStatus;
use App\Modules\Payroll\Models\AttendanceDay;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 8.5: the nightly leave jobs: AccrueEarnedLeave (hrm:accrue-leave) and CarryForwardLeave
 * (hrm:carry-forward-leave). The earning formulas themselves: tests/Unit/LeaveEarningTest.php. [LV-05]
 */
class LeaveAccrualTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private Employee $karim;

    private LeaveType $casual;

    private LeaveProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createMigratedTenant(['modules' => ['hrm', 'payroll', 'workflow']]);
        $this->actingAsTenantUser(tenant: $this->tenant);

        $this->casual = LeaveType::factory()->create(['code' => 'CL', 'name' => 'Casual leave']);   // Fixed
        $this->profile = LeaveProfile::factory()->create();
        $this->profile->syncEntitlements([$this->casual->id => 10]);
        $this->karim = Employee::factory()->create(['join_date' => '2020-01-01', 'leave_profile_id' => $this->profile->id]);
    }

    private function accrue(string $today): array
    {
        return app(AccrueEarnedLeave::class)->run(CarbonImmutable::parse($today));
    }

    private function balance(LeaveType $type, int $year): float
    {
        return app(LeaveLedger::class)->balance($this->karim, $type, LeaveYear::of($year));
    }

    public function test_the_years_entitlement_is_credited_once(): void
    {
        $this->assertSame(['periods' => 1, 'days' => 10.0], $this->accrue('2026-11-12'));

        $this->assertSame(10.0, $this->balance($this->casual, 2026));
        $accrual = LeaveAccrual::sole();
        $this->assertSame('Leave year 2026', $accrual->basis);
        $entry = LeaveLedgerEntry::sole();
        $this->assertSame(LeaveEntryType::Earned, $entry->entry_type);
        $this->assertSame($accrual->id, $entry->source_id);
        $this->assertSame($entry->id, $accrual->leave_ledger_id);
        $this->assertSame('2026-01-01', $entry->entry_date->toDateString());

        // Every night again: nothing new.
        $this->assertSame(['periods' => 0, 'days' => 0.0], $this->accrue('2026-11-13'));
        $this->assertSame(1, LeaveLedgerEntry::count());

        // New year: the new year's days.
        $this->accrue('2027-01-01');
        $this->assertSame(10.0, $this->balance($this->casual, 2027));
    }

    public function test_days_worked_come_from_processed_attendance(): void
    {
        $earned = LeaveType::factory()->create(['code' => 'EL', 'earn_type' => LeaveEarnType::PerDaysWorked, 'earn_per_days_worked' => 18]);
        $this->profile->syncEntitlements([$earned->id => 0]);   // in the profile; days come from work, not from the profile

        foreach (range(1, 18) as $day) {
            AttendanceDay::factory()->for($this->karim)->create(['date' => sprintf('2026-11-%02d', $day), 'status' => AttendanceStatus::Present]);
        }
        AttendanceDay::factory()->for($this->karim)->create(['date' => '2026-11-19', 'status' => AttendanceStatus::HalfDay]);
        AttendanceDay::factory()->for($this->karim)->create(['date' => '2026-11-20', 'status' => AttendanceStatus::Absent]);

        $this->accrue('2026-12-01');

        $november = LeaveAccrual::query()->whereDate('period_start', '2026-11-01')->sole();
        $this->assertSame(1.03, $november->days);   // 18.5 ÷ 18
        $this->assertSame('18.5 days worked in Nov 2026 ÷ 18', $november->basis);
        // October had no attendance: done with 0 days, without a ledger row.
        $october = LeaveAccrual::query()->whereDate('period_start', '2026-10-01')->sole();
        $this->assertSame(0.0, $october->days);
        $this->assertNull($october->leave_ledger_id);
        $this->assertSame(1.03, $this->balance($earned, 2026));
    }

    public function test_skipped_inactive_types_people_without_a_profile_and_before_go_live(): void
    {
        Employee::factory()->create(['join_date' => '2020-01-01', 'leave_profile_id' => null]);
        $this->casual->update(['is_active' => false]);
        $this->assertSame(0, $this->accrue('2026-11-12')['periods']);

        $this->casual->update(['is_active' => true]);
        settings()->set(AccrueEarnedLeave::EARN_FROM, '2026-06-01');   // 2026 started before go-live: opening balances cover it
        $this->assertSame(0, $this->accrue('2026-11-12')['periods']);
        $this->assertSame(1, $this->accrue('2027-01-01')['periods']);
    }

    public function test_the_command_runs_for_the_company(): void
    {
        $this->artisan('hrm:accrue-leave', ['--date' => '2026-11-12'])->assertSuccessful()->expectsOutputToContain('Credited 1 periods');
        $this->assertSame(10.0, $this->balance($this->casual, 2026));
    }

    public function test_the_year_is_closed_after_the_grace_days_carrying_forward_up_to_the_limit(): void
    {
        $this->casual->update(['can_carry_forward' => true, 'carry_forward_limit' => 5, 'carry_forward_expiry_months' => 3]);
        $this->accrue('2026-11-12');
        app(LeaveLedger::class)->debit($this->karim, $this->casual, LeaveYear::of(2026), 2, LeaveEntryType::Taken);   // 8 left

        $yearEnd = app(CarryForwardLeave::class);
        $this->assertSame(0, $yearEnd->run(CarbonImmutable::parse('2027-01-15'))['closed'], '15 days of grace (default).');

        $result = $yearEnd->run(CarbonImmutable::parse('2027-01-16'));
        $this->assertSame(1, $result['closed']);
        $this->assertSame(5.0, $result['carried']);
        $this->assertSame(0.0, $this->balance($this->casual, 2026));
        $this->assertSame(3.0, LeaveBalance::where('year', 2026)->sole()->expired);
        $this->assertSame(5.0, $this->balance($this->casual, 2027));

        // Again: nothing more. On 1 April the carried days that were not used expire.
        $yearEnd->run(CarbonImmutable::parse('2027-01-17'));
        $this->assertSame(5.0, $this->balance($this->casual, 2027));
        $this->assertSame(1, $yearEnd->run(CarbonImmutable::parse('2027-04-01'))['expired']);
        $this->assertSame(0.0, $this->balance($this->casual, 2027));
    }

    public function test_a_waiting_application_holds_the_year_open(): void
    {
        $this->accrue('2026-11-12');
        $waiting = LeaveApplication::create([
            'employee_id' => $this->karim->id, 'leave_type_id' => $this->casual->id, 'from_date' => '2026-12-28', 'to_date' => '2026-12-28',
            'days' => 1, 'leave_year' => 2026,
        ]);

        $result = app(CarryForwardLeave::class)->run(CarbonImmutable::parse('2027-01-20'));
        $this->assertSame([0, 1], [$result['closed'], $result['waiting']]);
        $this->assertSame(10.0, $this->balance($this->casual, 2026));

        $waiting->forceFill(['rejected_at' => now()])->save();   // decided
        $this->assertSame(1, app(CarryForwardLeave::class)->run(CarbonImmutable::parse('2027-01-21'))['closed']);
        $this->assertSame(0.0, $this->balance($this->casual, 2026));   // no carry forward for this type: expired
    }

    public function test_no_leave_can_be_entered_in_a_closed_year(): void
    {
        $this->travelTo('2027-01-20 10:00');
        $values = ['from_date' => '2026-12-21', 'to_date' => '2026-12-21', 'reason' => 'Late entry'];

        $check = app(ApplyForLeave::class)->evaluate($this->karim, $this->casual, $values, byHr: true);
        $this->assertContains('year_closed', $check->codes());

        $this->travelTo('2027-01-10 10:00');   // still within the 15 days
        $this->assertNotContains('year_closed', app(ApplyForLeave::class)->evaluate($this->karim, $this->casual, $values, byHr: true)->codes());
    }

    public function test_the_settings_are_saved(): void
    {
        Livewire::test(LeaveSettings::class)
            ->assertSet('closeAfterDays', 15)
            ->set('earnFrom', '2026-07-01')
            ->set('closeAfterDays', 30)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('2026-07-01', settings(AccrueEarnedLeave::EARN_FROM));
        $this->assertSame(30, CarryForwardLeave::closeAfterDays());

        Livewire::test(LeaveSettings::class)->set('closeAfterDays', 200)->call('save')->assertHasErrors(['closeAfterDays']);
    }
}
