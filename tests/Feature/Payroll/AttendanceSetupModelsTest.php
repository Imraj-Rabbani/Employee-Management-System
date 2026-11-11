<?php

namespace Tests\Feature\Payroll;

use App\Core\Tenancy\TenantManager;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Enums\Weekday;
use App\Modules\Payroll\Enums\WeekParity;
use App\Modules\Payroll\Models\Holiday;
use App\Modules\Payroll\Models\HolidayProfile;
use App\Modules\Payroll\Models\Shift;
use App\Modules\Payroll\Models\WeekendProfile;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesTenantDatabases;
use Tests\TestCase;

/**
 * Task 7.1: shifts, weekend profiles, holidays, holiday calendars. [TA-01, TA-02]
 */
class AttendanceSetupModelsTest extends TestCase
{
    use CreatesTenantDatabases;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantManager::class)->switchTo($this->createMigratedTenant());
    }

    public function test_all_models_live_in_the_tenant_database(): void
    {
        foreach ([Shift::class, WeekendProfile::class, Holiday::class, HolidayProfile::class] as $model) {
            $this->assertSame('tenant', $model::factory()->create()->getConnectionName(), $model);
        }
    }

    public function test_overnight_is_worked_out_from_the_times(): void
    {
        $day = Shift::factory()->create(['start_time' => '09:00', 'end_time' => '18:00', 'is_overnight' => true]);
        $night = Shift::factory()->create(['start_time' => '22:00', 'end_time' => '06:00']);

        $this->assertFalse($day->fresh()->is_overnight);   // the wrong value given was corrected
        $this->assertTrue($night->fresh()->is_overnight);
        $this->assertSame('22:00–06:00 (+1)', $night->fresh()->timesLabel());
    }

    public function test_there_is_only_one_default_shift(): void
    {
        $general = Shift::factory()->create(['is_default' => true]);
        $morning = Shift::factory()->create(['is_default' => true]);

        $this->assertFalse($general->fresh()->is_default);
        $this->assertTrue(Shift::default()->is($morning));
    }

    public function test_weekend_days_every_week(): void
    {
        $profile = WeekendProfile::factory()->create(['day_one' => Weekday::Friday, 'day_two' => Weekday::Saturday]);

        $this->assertTrue($profile->isWeekend(Carbon::parse('2026-11-13')));    // Friday
        $this->assertTrue($profile->isWeekend(Carbon::parse('2026-11-14')));    // Saturday
        $this->assertFalse($profile->isWeekend(Carbon::parse('2026-11-15')));   // Sunday
        $this->assertSame('Friday, Saturday', $profile->summary());
    }

    public function test_an_alternating_weekend_day_counts_its_occurrences_from_1_january(): void
    {
        // Every Friday, and Saturday only on the even Saturdays of the year.
        $profile = WeekendProfile::factory()->create([
            'day_one' => Weekday::Friday, 'day_two' => Weekday::Saturday, 'alternate_two' => WeekParity::Even,
        ]);

        // 2026: 3 Jan is the 1st Saturday, 10 Jan the 2nd, 17 Jan the 3rd.
        $this->assertFalse($profile->isWeekend(Carbon::parse('2026-01-03')));
        $this->assertTrue($profile->isWeekend(Carbon::parse('2026-01-10')));
        $this->assertFalse($profile->isWeekend(Carbon::parse('2026-01-17')));
        $this->assertTrue($profile->isWeekend(Carbon::parse('2026-01-16')));    // Friday: every week
        $this->assertSame('Friday, Saturday (even weeks)', $profile->summary());
    }

    public function test_holidays_on_a_date_include_yearly_ones_from_their_first_year_on(): void
    {
        $motherLanguage = Holiday::factory()->create(['date' => '2025-02-21', 'title' => 'Language Day', 'repeats_yearly' => true]);
        $eid = Holiday::factory()->create(['date' => '2026-03-20', 'title' => 'Eid']);

        $this->assertTrue(Holiday::onDate(Carbon::parse('2027-02-21'))->sole()->is($motherLanguage));
        $this->assertTrue(Holiday::onDate(Carbon::parse('2026-03-20'))->sole()->is($eid));
        $this->assertSame(0, Holiday::onDate(Carbon::parse('2024-02-21'))->count());   // before it started
        $this->assertSame(0, Holiday::onDate(Carbon::parse('2027-03-20'))->count());   // Eid moves: not yearly

        $this->assertEqualsCanonicalizing([$motherLanguage->id, $eid->id], Holiday::inYear(2026)->pluck('id')->all());
        $this->assertSame([$motherLanguage->id], Holiday::inYear(2027)->pluck('id')->all());
    }

    public function test_a_holiday_counts_only_in_the_calendars_that_contain_it(): void
    {
        $victory = Holiday::factory()->create(['date' => '2026-12-16', 'title' => 'Victory Day']);
        $office = HolidayProfile::factory()->hasAttached($victory)->create(['name' => 'Head office']);
        $factory = HolidayProfile::factory()->create(['name' => 'Factory']);

        $this->assertTrue($office->holidayOn(Carbon::parse('2026-12-16'))?->is($victory));
        $this->assertNull($factory->holidayOn(Carbon::parse('2026-12-16')));
        $this->assertNull($office->holidayOn(Carbon::parse('2026-12-17')));
    }

    public function test_setup_rows_used_by_employees_cannot_be_deleted(): void
    {
        $shift = Shift::factory()->create();
        $weekend = WeekendProfile::factory()->create();
        $calendar = HolidayProfile::factory()->create();
        $employee = Employee::factory()->create([
            'shift_id' => $shift->id, 'weekend_profile_id' => $weekend->id, 'holiday_profile_id' => $calendar->id,
        ]);

        $this->assertTrue($employee->shift->is($shift));
        $this->assertTrue($employee->weekendProfile->is($weekend));
        $this->assertTrue($employee->holidayProfile->is($calendar));

        foreach ([$shift, $weekend, $calendar] as $row) {
            try {
                $row->delete();
                $this->fail($row::class.' should not be deletable while an employee uses it.');
            } catch (QueryException $e) {
                $this->assertSame('23000', $e->getCode());
            }
        }
    }
}
