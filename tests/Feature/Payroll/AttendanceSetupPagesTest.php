<?php

namespace Tests\Feature\Payroll;

use App\Models\Tenant;
use App\Modules\Hrm\Livewire\Employees\JobForm;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Enums\Weekday;
use App\Modules\Payroll\Enums\WeekParity;
use App\Modules\Payroll\Livewire\Attendance\HolidayForm;
use App\Modules\Payroll\Livewire\Attendance\HolidayProfileForm;
use App\Modules\Payroll\Livewire\Attendance\ShiftForm;
use App\Modules\Payroll\Livewire\Attendance\WeekendProfileForm;
use App\Modules\Payroll\Models\Holiday;
use App\Modules\Payroll\Models\HolidayProfile;
use App\Modules\Payroll\Models\Shift;
use App\Modules\Payroll\Models\WeekendProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 7.1: Payroll → Attendance setup pages, and assigning them on the employee's Job tab. [TA-01, TA-02]
 */
class AttendanceSetupPagesTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createMigratedTenant();
        $this->actingAsTenantUser(tenant: $this->tenant);   // Administrator
    }

    public function test_every_section_opens_and_needs_the_permission(): void
    {
        Shift::factory()->create(['name' => 'Night shift']);
        WeekendProfile::factory()->create(['name' => 'Fri-Sat']);
        Holiday::factory()->create(['title' => 'Victory Day', 'date' => now()->toDateString()]);
        HolidayProfile::factory()->create(['name' => 'Head office']);

        $this->get('/payroll/attendance')->assertRedirect(route('payroll.attendance.shifts'));
        $this->get('/payroll/attendance/shifts')->assertOk()->assertSee('Night shift')->assertSee(route('payroll.attendance.holidays'));
        $this->get('/payroll/attendance/weekends')->assertOk()->assertSee('Fri-Sat');
        $this->get('/payroll/attendance/holidays')->assertOk()->assertSee('Victory Day');
        $this->get('/payroll/attendance/holiday-profiles')->assertOk()->assertSee('Head office');

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['payroll.attendance.setup']);
        $this->get('/payroll/attendance/shifts')->assertOk();

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: []);
        $this->get('/payroll/attendance/shifts')->assertForbidden();
        $this->get('/payroll/attendance')->assertForbidden();
    }

    public function test_a_shift_is_created_and_edited(): void
    {
        Livewire::test(ShiftForm::class)
            ->dispatch('create-shift')
            ->set('form.code', 'N')
            ->set('form.name', 'Night')
            ->set('form.start_time', '22:00')
            ->set('form.end_time', '06:00')
            ->set('form.grace_minutes', '10')
            ->set('form.is_default', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('shift-saved');

        $shift = Shift::sole();
        $this->assertTrue($shift->is_overnight);
        $this->assertTrue($shift->is_default);
        $this->assertNull($shift->org_unit_id);
        $this->assertNull($shift->grace_limit_per_month);

        // The database gives 22:00:00; the form shows 22:00 so the H:i rule passes untouched.
        Livewire::test(ShiftForm::class)
            ->dispatch('edit-shift', id: $shift->id)
            ->assertSet('form.start_time', '22:00')
            ->set('form.end_time', '07:00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('07:00:00', $shift->fresh()->end_time);
    }

    public function test_shift_rules(): void
    {
        Livewire::test(ShiftForm::class)
            ->dispatch('create-shift')
            ->set('form.code', 'G')
            ->set('form.name', 'General')
            ->set('form.end_time', '09:00')                 // same as the start
            ->set('form.half_day_hours', '9')               // not below the 8 hours
            ->set('form.geofence_enabled', true)            // but no location
            ->set('form.is_default', true)
            ->set('form.is_active', false)
            ->call('save')
            ->assertHasErrors(['form.end_time', 'form.half_day_hours', 'form.geofence_lat', 'form.geofence_lng', 'form.geofence_radius_m'])
            ->set('form.end_time', '18:00')
            ->set('form.half_day_hours', '4')
            ->set('form.geofence_lat', '23.7808875')
            ->set('form.geofence_lng', '90.2792371')
            ->set('form.geofence_radius_m', '200')
            ->call('save')
            ->assertHasErrors(['form.is_default'])         // an inactive default makes no sense
            ->set('form.is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('23.7808875', Shift::sole()->geofence_lat);
    }

    public function test_a_shift_in_use_is_not_deleted(): void
    {
        $shift = Shift::factory()->create(['name' => 'General']);
        Employee::factory()->create(['shift_id' => $shift->id]);

        Livewire::test(ShiftForm::class)
            ->dispatch('edit-shift', id: $shift->id)
            ->call('delete')
            ->assertHasErrors(['form.code']);

        $this->assertModelExists($shift);
    }

    public function test_a_weekend_profile_is_saved_and_reopened(): void
    {
        Livewire::test(WeekendProfileForm::class)
            ->dispatch('create-weekend-profile')
            ->set('form.name', 'Friday + 2nd Saturday')
            ->set('form.day_one', '5')
            ->set('form.day_two', '6')
            ->set('form.alternate_two', '2')
            ->set('form.home_office_day', '6')               // Saturday is a weekend day
            ->call('save')
            ->assertHasErrors(['form.home_office_day'])
            ->set('form.home_office_day', '0')               // Sunday (0 must not be taken for "empty")
            ->call('save')
            ->assertHasNoErrors();

        $profile = WeekendProfile::sole();
        $this->assertSame(Weekday::Saturday, $profile->day_two);
        $this->assertSame(WeekParity::Even, $profile->alternate_two);
        $this->assertSame(Weekday::Sunday, $profile->home_office_day);
        $this->assertNull($profile->alternate_one);

        Livewire::test(WeekendProfileForm::class)
            ->dispatch('edit-weekend-profile', id: $profile->id)
            ->assertSet('form.day_two', 6)
            ->assertSet('form.alternate_one', '')
            ->set('form.day_one', '')
            ->set('form.alternate_one', '1')                 // odd weeks of … no day
            ->call('save')
            ->assertHasErrors(['form.alternate_one']);
    }

    public function test_a_holiday_is_put_in_calendars(): void
    {
        $office = HolidayProfile::factory()->create(['name' => 'Head office']);
        $plant = HolidayProfile::factory()->create(['name' => 'Factory']);
        HolidayProfile::factory()->create(['name' => 'Old', 'is_active' => false]);

        // New holiday: every ACTIVE calendar is ticked to start with.
        Livewire::test(HolidayForm::class)
            ->dispatch('create-holiday')
            ->assertSet('profileIds', [(string) $office->id, (string) $plant->id])
            ->set('form.date', '2026-12-16')
            ->set('form.title', 'Victory Day')
            ->set('profileIds', [(string) $office->id])
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('holiday-saved');

        $holiday = Holiday::sole();
        $this->assertSame([$office->id], $holiday->profiles()->pluck('holiday_profiles.id')->all());

        Livewire::test(HolidayForm::class)
            ->dispatch('edit-holiday', id: $holiday->id)
            ->assertSet('profileIds', [(string) $office->id])
            ->set('profileIds', [(string) $plant->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([$plant->id], $holiday->profiles()->pluck('holiday_profiles.id')->all());

        // Same title on the same date twice is refused.
        Livewire::test(HolidayForm::class)
            ->dispatch('create-holiday')
            ->set('form.date', '2026-12-16')
            ->set('form.title', 'Victory Day')
            ->call('save')
            ->assertHasErrors(['form.title']);
    }

    public function test_deleting_a_calendar_keeps_its_holidays(): void
    {
        $holiday = Holiday::factory()->create();
        $calendar = HolidayProfile::factory()->hasAttached($holiday)->create();

        Livewire::test(HolidayProfileForm::class)
            ->dispatch('edit-holiday-profile', id: $calendar->id)
            ->call('delete')
            ->assertHasNoErrors();

        $this->assertModelMissing($calendar);
        $this->assertModelExists($holiday);
    }

    public function test_shift_weekend_and_calendar_are_assigned_on_the_job_tab(): void
    {
        $default = Shift::factory()->create(['name' => 'General', 'is_default' => true]);
        $night = Shift::factory()->overnight()->create(['name' => 'Night']);
        $weekend = WeekendProfile::factory()->create(['name' => 'Fri-Sat']);
        $calendar = HolidayProfile::factory()->create(['name' => 'Factory']);
        $employee = Employee::factory()->create();

        // No own shift yet: the Job tab shows the default.
        $this->get(route('hrm.employees.show', ['employee' => $employee, 'tab' => 'job']))
            ->assertOk()->assertSee('Default (General · 09:00–18:00)');

        Livewire::test(JobForm::class, ['employee' => $employee])
            ->call('open')
            ->set('form.shift_id', (string) $night->id)
            ->set('form.has_shifting', true)
            ->set('form.weekend_profile_id', (string) $weekend->id)
            ->set('form.holiday_profile_id', (string) $calendar->id)
            ->call('save')
            ->assertHasNoErrors();

        $employee->refresh();
        $this->assertSame($night->id, $employee->shift_id);
        $this->assertTrue($employee->has_shifting);
        $this->assertSame($weekend->id, $employee->weekend_profile_id);
        $this->assertSame($calendar->id, $employee->holiday_profile_id);
        $this->assertNotSame($default->id, $employee->shift_id);

        $this->get(route('hrm.employees.show', ['employee' => $employee, 'tab' => 'job']))
            ->assertSee('Night · 22:00–06:00 (+1)')->assertSee('Fri-Sat')->assertSee('Factory');
    }
}
