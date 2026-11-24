<?php

namespace Tests\Feature\Hrm;

use App\Modules\Hrm\Data\LeaveYear;
use App\Modules\Hrm\Enums\LeaveEarnType;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveLedgerEntry;
use App\Modules\Hrm\Models\LeaveProfile;
use App\Modules\Hrm\Models\LeaveType;
use App\Modules\Hrm\Services\AccrueEarnedLeave;
use App\Modules\Hrm\Services\LeaveLedger;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Leave that is due is credited the moment it becomes due because of a change (CreditLeaveNow),
 * not only by the nightly hrm:accrue-leave.
 */
class CreditLeaveNowTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private LeaveType $casual;

    private LeaveProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-24 10:00'));
        $this->actingAsTenantUser(tenant: $this->createMigratedTenant(['modules' => ['hrm', 'payroll', 'workflow']]));

        $this->casual = LeaveType::factory()->create(['code' => 'CL', 'name' => 'Casual leave']);   // Fixed: the year's days at once
        $this->profile = LeaveProfile::factory()->create();
        $this->profile->syncEntitlements([$this->casual->id => 12]);
    }

    private function balance(Employee $employee, LeaveType $type): float
    {
        return app(LeaveLedger::class)->balance($employee, $type, LeaveYear::of(2026));
    }

    public function test_a_new_employee_with_a_profile_has_the_days_at_once(): void
    {
        $karim = Employee::factory()->create(['join_date' => '2025-06-10', 'leave_profile_id' => $this->profile->id]);

        $this->assertSame(12.0, $this->balance($karim, $this->casual));
    }

    public function test_giving_an_employee_a_profile_later_credits_at_once(): void
    {
        $karim = Employee::factory()->create(['join_date' => '2025-06-10']);
        $this->assertSame(0.0, $this->balance($karim, $this->casual));

        $karim->update(['leave_profile_id' => $this->profile->id]);

        $this->assertSame(12.0, $this->balance($karim, $this->casual));
    }

    public function test_a_type_added_to_the_profile_is_credited_to_its_employees_and_never_twice(): void
    {
        $karim = Employee::factory()->create(['join_date' => '2025-06-10', 'leave_profile_id' => $this->profile->id]);
        $sick = LeaveType::factory()->create(['code' => 'SL', 'name' => 'Sick leave']);

        $this->profile->syncEntitlements([$this->casual->id => 12, $sick->id => 14]);
        $this->assertSame(14.0, $this->balance($karim, $sick));

        // Changing the days later does not rewrite what was credited (history); the nightly job has nothing to add.
        $this->profile->syncEntitlements([$this->casual->id => 12, $sick->id => 20]);
        $this->assertSame(14.0, $this->balance($karim, $sick));
        $this->assertSame(['periods' => 0, 'days' => 0.0], app(AccrueEarnedLeave::class)->run(CarbonImmutable::parse('2026-11-25')));
        $this->assertSame(2, LeaveLedgerEntry::count());
    }

    public function test_switching_a_type_on_credits_it(): void
    {
        $this->casual->update(['is_active' => false]);
        $karim = Employee::factory()->create(['join_date' => '2025-06-10', 'leave_profile_id' => $this->profile->id]);
        $this->assertSame(0.0, $this->balance($karim, $this->casual));

        $this->casual->update(['is_active' => true]);

        $this->assertSame(12.0, $this->balance($karim, $this->casual));
    }

    public function test_a_monthly_type_gets_only_the_months_already_due(): void
    {
        $monthly = LeaveType::factory()->create(['code' => 'EL', 'name' => 'Earned leave', 'earn_type' => LeaveEarnType::MonthlyAccrual]);
        $this->profile->syncEntitlements([$monthly->id => 12]);

        $karim = Employee::factory()->create(['join_date' => '2025-06-10', 'leave_profile_id' => $this->profile->id]);

        // Only the months the catch-up window covers (the nightly job does the same), never the whole year.
        $balance = $this->balance($karim, $monthly);
        $this->assertGreaterThan(0.0, $balance);
        $this->assertLessThan(12.0, $balance);
    }
}
