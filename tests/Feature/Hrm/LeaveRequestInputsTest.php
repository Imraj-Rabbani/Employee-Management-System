<?php

namespace Tests\Feature\Hrm;

use App\Modules\Hrm\Contracts\WorkCalendar;
use App\Modules\Hrm\Data\LeaveYear;
use App\Modules\Hrm\Enums\Gender;
use App\Modules\Hrm\Enums\LeaveEntryType;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveProfile;
use App\Modules\Hrm\Models\LeaveType;
use App\Modules\Hrm\Services\LeaveLedger;
use App\Modules\Hrm\Services\LeaveRequestInputs;
use App\Modules\Hrm\Services\LeaveRules;
use App\Modules\Payroll\Enums\Weekday;
use App\Modules\Payroll\Models\Holiday;
use App\Modules\Payroll\Models\HolidayProfile;
use App\Modules\Payroll\Models\Roster;
use App\Modules\Payroll\Models\RosterItem;
use App\Modules\Payroll\Models\Shift;
use App\Modules\Payroll\Models\WeekendProfile;
use App\Modules\Payroll\Services\AttendanceWorkCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 8.3: the database side of the leave rules: LeaveRequestInputs and the days off from the attendance
 * setup (WorkCalendar → AttendanceWorkCalendar). The rules themselves: tests/Unit/LeaveRulesTest.php. [LV-01]
 *
 *     December 2026:  Tue 1  Wed 2  Thu 3  Fri 4  Sat 5  Sun 6
 */
class LeaveRequestInputsTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Employee $employee;

    private LeaveType $casual;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsTenantUser(tenant: $this->createMigratedTenant());

        $calendar = HolidayProfile::factory()->create();
        $calendar->holidays()->attach(Holiday::factory()->create(['date' => '2026-12-02', 'title' => 'Holiday'])->id);

        $this->casual = LeaveType::factory()->create(['code' => 'CL', 'name' => 'Casual leave', 'exclude_holidays' => true]);
        $profile = LeaveProfile::factory()->create();
        $profile->syncEntitlements([$this->casual->id => 10]);

        $this->employee = Employee::factory()->create([
            'gender' => Gender::Female,
            'join_date' => '2024-03-01',
            'leave_profile_id' => $profile->id,
            'weekend_profile_id' => WeekendProfile::factory()->create(['day_one' => Weekday::Friday, 'day_two' => Weekday::Saturday])->id,
            'holiday_profile_id' => $calendar->id,
        ]);
    }

    public function test_the_payroll_module_answers_the_days_off(): void
    {
        $this->assertInstanceOf(AttendanceWorkCalendar::class, app(WorkCalendar::class));
    }

    public function test_days_off_come_from_holidays_weekends_and_the_approved_roster(): void
    {
        // An approved roster makes Thursday 3 a day off and Saturday 5 a working day.
        $roster = Roster::factory()->approved()->create(['from_date' => '2026-12-01', 'to_date' => '2026-12-06']);
        RosterItem::factory()->for($roster)->create(['employee_id' => $this->employee->id, 'date' => '2026-12-03', 'shift_id' => null, 'is_off' => true]);
        RosterItem::factory()->for($roster)->create(['employee_id' => $this->employee->id, 'date' => '2026-12-05', 'shift_id' => Shift::factory()->create()->id]);

        $off = app(WorkCalendar::class)->daysOff($this->employee, CarbonImmutable::parse('2026-12-01'), CarbonImmutable::parse('2026-12-06'));

        $this->assertSame(['2026-12-02', '2026-12-03', '2026-12-04'], array_keys($off));   // holiday, roster off, Friday
    }

    public function test_the_request_is_built_from_the_database(): void
    {
        app(LeaveLedger::class)->credit($this->employee, $this->casual, LeaveYear::containing('2026-12-01'), 4, LeaveEntryType::Opening);

        $request = app(LeaveRequestInputs::class)->build($this->employee, $this->casual, CarbonImmutable::parse('2026-12-01'), CarbonImmutable::parse('2026-12-06'),
            timesTaken: 1, overlaps: false, hasReason: true, today: CarbonImmutable::parse('2026-11-20'));

        $this->assertSame(Gender::Female, $request->gender);
        $this->assertSame('2024-03-01', $request->joinDate?->toDateString());
        $this->assertSame(10.0, $request->entitlement);
        $this->assertSame(4.0, $request->balance);
        $this->assertSame(1, $request->timesTaken);
        $this->assertSame('2026-11-20', $request->today->toDateString());
        $this->assertArrayHasKey('2026-11-27', $request->daysOff, 'Days off around the leave are loaded too (sandwich rule).');

        // Tue 1 – Sun 6 without the holiday (Wed 2) and the weekend (Fri 4, Sat 5) = 3 days, 4 available.
        $check = app(LeaveRules::class)->check($this->casual, $request);
        $this->assertTrue($check->ok(), implode(' ', $check->messages()));
        $this->assertSame(['2026-12-01', '2026-12-03', '2026-12-06'], array_keys($check->dates));
    }

    public function test_a_type_outside_the_profile_and_an_empty_balance(): void
    {
        $sick = LeaveType::factory()->create(['name' => 'Sick leave']);

        $request = app(LeaveRequestInputs::class)->build($this->employee, $sick, CarbonImmutable::parse('2026-12-01'), CarbonImmutable::parse('2026-12-01'),
            timesTaken: 0, overlaps: false, today: CarbonImmutable::parse('2026-11-20'));

        $this->assertNull($request->entitlement);
        $this->assertSame(0.0, $request->balance);
        $this->assertSame(['not_in_profile', 'balance'], app(LeaveRules::class)->check($sick, $request)->codes());
    }

    public function test_an_employee_without_a_leave_profile(): void
    {
        $this->employee->update(['leave_profile_id' => null]);

        $request = app(LeaveRequestInputs::class)->build($this->employee->fresh(), $this->casual, CarbonImmutable::parse('2026-12-01'), CarbonImmutable::parse('2026-12-01'),
            timesTaken: 0, overlaps: false);

        $this->assertNull($request->entitlement);
    }
}
