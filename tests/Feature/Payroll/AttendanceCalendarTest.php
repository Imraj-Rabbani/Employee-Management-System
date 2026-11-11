<?php

namespace Tests\Feature\Payroll;

use App\Models\Tenant;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Payroll\Enums\AttendanceStatus;
use App\Modules\Payroll\Enums\Weekday;
use App\Modules\Payroll\Livewire\Attendance\EmployeeCalendar;
use App\Modules\Payroll\Livewire\Attendance\TeamCalendar;
use App\Modules\Payroll\Models\AttendanceDay;
use App\Modules\Payroll\Models\Holiday;
use App\Modules\Payroll\Models\HolidayProfile;
use App\Modules\Payroll\Models\Shift;
use App\Modules\Payroll\Models\WeekendProfile;
use App\Modules\Payroll\Services\AttendanceCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 7.8: attendance calendars — my month (Self Service), my team (managers), and HR's Calendar tab. [TA-08]
 *
 * Today = Thursday 10 December 2026. December 2026 starts on a Tuesday.
 */
class AttendanceCalendarTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private Employee $boss;

    private Employee $lead;

    private Employee $worker;

    private Employee $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-10 12:00');
        $this->tenant = $this->createMigratedTenant(['modules' => ['hrm', 'payroll', 'workflow']]);
        $this->actingAsTenantUser(tenant: $this->tenant);   // Administrator

        Shift::factory()->create(['code' => 'G', 'start_time' => '09:00', 'end_time' => '18:00', 'is_default' => true]);
        $weekend = WeekendProfile::factory()->create(['day_one' => Weekday::Friday, 'day_two' => Weekday::Saturday]);
        $calendar = HolidayProfile::factory()->create();
        $calendar->holidays()->attach(Holiday::factory()->create(['date' => '2026-12-16', 'title' => 'Victory Day'])->id);

        $common = ['join_date' => '2026-01-01', 'weekend_profile_id' => $weekend->id, 'holiday_profile_id' => $calendar->id];
        $this->boss = Employee::factory()->create([...$common, 'code' => 'E-001', 'first_name' => 'Boss']);
        $this->lead = Employee::factory()->create([...$common, 'code' => 'E-002', 'first_name' => 'Lead', 'supervisor_id' => $this->boss->id]);
        $this->worker = Employee::factory()->create([...$common, 'code' => 'E-003', 'first_name' => 'Worker', 'supervisor_id' => $this->lead->id]);
        $this->stranger = Employee::factory()->create([...$common, 'code' => 'E-009', 'first_name' => 'Stranger']);

        AttendanceDay::factory()->for($this->worker)->create(['date' => '2026-12-01', 'in_at' => '2026-12-01 09:20', 'out_at' => '2026-12-01 18:00', 'is_late' => true]);
        AttendanceDay::factory()->for($this->worker)->absent()->create(['date' => '2026-12-02']);
    }

    public function test_the_month_of_one_employee_mixes_processed_days_and_the_plan(): void
    {
        $weeks = app(AttendanceCalendar::class)->employeeMonth($this->worker, CarbonImmutable::parse('2026-12-01'));
        $cells = collect($weeks)->flatten(1)->keyBy(fn ($c) => $c['date']->toDateString());

        $this->assertSame('2026-11-30', $weeks[0][0]['date']->toDateString());   // weeks start on Monday
        $this->assertFalse($cells['2026-11-30']['inMonth']);
        $this->assertSame(AttendanceStatus::Absent, $cells['2026-12-02']['day']?->status);
        $this->assertTrue($cells['2026-12-10']['isToday']);
        $this->assertSame('Victory Day', $cells['2026-12-16']['holiday']);
        $this->assertSame('G', $cells['2026-12-17']['plannedShift']);              // a future workday: its shift
        $this->assertSame('Weekend', $cells['2026-12-18']['plannedType']?->name);   // Friday
    }

    public function test_my_attendance_shows_my_calendar(): void
    {
        $me = $this->actingAsTenantUser(tenant: $this->tenant, permissions: []);
        $this->worker->forceFill(['user_id' => $me->id])->save();

        $this->get(route('ess.attendance'))->assertOk()->assertSee('My calendar')->assertSee('December 2026')->assertSee('09:20');

        Livewire::test(EmployeeCalendar::class, ['mode' => 'self'])
            ->assertSee('Victory Day')->assertSee('Late')
            ->call('previous')->assertSet('month', '2026-11')->assertSee('November 2026')
            ->call('thisMonth')->assertSet('month', '2026-12');
    }

    public function test_a_manager_sees_everyone_below_them_and_nobody_else(): void
    {
        $me = $this->actingAsTenantUser(tenant: $this->tenant, permissions: []);
        $this->boss->forceFill(['user_id' => $me->id])->save();

        $this->assertEqualsCanonicalizing([$this->lead->id, $this->worker->id], $this->boss->teamIds());

        $this->get(route('ess.team-attendance'))->assertOk()
            ->assertSee('E-002')->assertSee('E-003')->assertDontSee('E-009')->assertDontSee('E-001 ·');
        $this->get(route('ess.team-attendance', ['employee' => $this->worker->id]))->assertOk()->assertSee('Worker')->assertSee('09:20');
        $this->get(route('ess.team-attendance', ['employee' => $this->stranger->id]))->assertNotFound();

        // The worker has nobody below them.
        $this->boss->forceFill(['user_id' => null])->save();
        $this->worker->forceFill(['user_id' => $me->id])->save();
        $this->get(route('ess.team-attendance'))->assertOk()->assertSee('Nobody reports to you');
    }

    public function test_hr_sees_the_grid_of_visible_employees(): void
    {
        $this->get(route('payroll.attendance.calendar'))->assertOk()->assertSee('E-009')->assertSee('E-003');
        Livewire::test(TeamCalendar::class, ['mode' => 'hr'])->set('search', 'Worker')->assertSee('E-003')->assertDontSee('E-009');
        $this->get(route('payroll.attendance.calendar', ['employee' => $this->worker->id]))->assertOk()->assertSee('Back to the team')->assertSee('09:20');

        // Limited to one org unit: others are not in the grid and cannot be opened.
        $unit = OrgUnit::factory()->create();
        $this->stranger->update(['org_unit_id' => $unit->id]);
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['payroll.attendance.process']);
        Role::where('name', 'like', 'Test role%')->latest('id')->firstOrFail()->update(['org_unit_id' => $unit->id]);
        $this->get(route('payroll.attendance.calendar'))->assertOk()->assertSee('E-009')->assertDontSee('E-003');
        $this->get(route('payroll.attendance.calendar', ['employee' => $this->worker->id]))->assertNotFound();

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['payroll.punch.manage']);
        $this->get(route('payroll.attendance.calendar'))->assertForbidden();
    }

    public function test_a_supervisor_loop_in_the_data_does_not_hang(): void
    {
        $this->boss->update(['supervisor_id' => $this->worker->id]);   // worker → lead → boss → worker

        $this->assertEqualsCanonicalizing([$this->lead->id, $this->worker->id], $this->boss->teamIds());
    }
}
