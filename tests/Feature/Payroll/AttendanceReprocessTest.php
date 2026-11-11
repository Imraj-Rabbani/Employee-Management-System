<?php

namespace Tests\Feature\Payroll;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Payroll\Actions\StorePunches;
use App\Modules\Payroll\Enums\AttendanceStatus;
use App\Modules\Payroll\Enums\PunchSource;
use App\Modules\Payroll\Jobs\ProcessAttendance;
use App\Modules\Payroll\Livewire\Attendance\AttendanceDayTable;
use App\Modules\Payroll\Livewire\Attendance\AttendanceReprocess;
use App\Modules\Payroll\Livewire\Attendance\HolidayForm;
use App\Modules\Payroll\Models\AttendanceDay;
use App\Modules\Payroll\Models\Holiday;
use App\Modules\Payroll\Models\HolidayProfile;
use App\Modules\Payroll\Models\PunchLog;
use App\Modules\Payroll\Models\Roster;
use App\Modules\Payroll\Models\RosterItem;
use App\Modules\Payroll\Models\Shift;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Enums\ApproverType;
use App\Modules\Workflow\Models\Approval;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowStep;
use App\Modules\Workflow\Services\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 7.5: "Process again" on the Daily attendance tab, and the changes that reprocess by themselves
 * (roster approved, holiday saved / deleted, new or matched punches). [TA-05, TA-06]
 *
 * Today = Thursday 10 December 2026.
 */
class AttendanceReprocessTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private Employee $karim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-10 12:00');
        $this->tenant = $this->createMigratedTenant(['modules' => ['hrm', 'payroll', 'workflow']]);
        $this->actingAsTenantUser(tenant: $this->tenant);   // Administrator

        Shift::factory()->create(['code' => 'G', 'start_time' => '09:00', 'end_time' => '18:00', 'is_default' => true]);
        $this->karim = Employee::factory()->create(['join_date' => '2026-01-01', 'access_code' => '1001']);
    }

    /** @return array<int, array{0: string, 1: string}> employee id => [from, to] of the queued jobs */
    private function queued(): array
    {
        $jobs = [];
        Queue::assertPushed(ProcessAttendance::class, function (ProcessAttendance $job) use (&$jobs) {
            $jobs[$job->employeeId][] = [$job->from, $job->to];

            return true;
        });

        return $jobs;
    }

    // ---- Daily attendance tab -----------------------------------------------------------------

    public function test_the_tab_needs_its_permission_and_lists_visible_days(): void
    {
        AttendanceDay::factory()->for($this->karim)->create(['date' => '2026-12-09', 'status' => AttendanceStatus::Absent]);

        $this->get(route('payroll.attendance.days'))->assertOk()->assertSee('Daily attendance')->assertSee('Process again');
        Livewire::test(AttendanceDayTable::class)->assertSee($this->karim->code)->assertSee('Absent');

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['payroll.punch.manage']);
        $this->get(route('payroll.attendance.days'))->assertForbidden();
        $this->get(route('payroll.attendance.punches'))->assertOk()->assertDontSee('Daily attendance');
    }

    public function test_process_again_queues_one_job_per_visible_employee_employed_in_the_range(): void
    {
        Queue::fake();
        $factory = OrgUnit::factory()->create(['name' => 'Factory']);
        $rahim = Employee::factory()->create(['join_date' => '2026-01-01', 'org_unit_id' => $factory->id]);
        Employee::factory()->create(['join_date' => '2026-12-09']);                                   // joined after the range
        Employee::factory()->create(['join_date' => '2025-01-01', 'separation_date' => '2026-10-31']);   // left before it

        Livewire::test(AttendanceReprocess::class)
            ->assertSet('from', '2026-12-01')->assertSet('to', '2026-12-09')   // this month up to yesterday
            ->set('to', '2026-12-05')
            ->call('run')
            ->assertHasNoErrors()
            ->assertDispatched('attendance-days-saved');
        $this->assertSame([$this->karim->id => [['2026-12-01', '2026-12-05']], $rahim->id => [['2026-12-01', '2026-12-05']]], $this->queued());

        // One org unit only.
        Queue::fake();
        Livewire::test(AttendanceReprocess::class)->set('orgUnitId', (string) $factory->id)->call('run');
        $this->assertSame([$rahim->id], array_keys($this->queued()));

        // A user limited to the Factory cannot process Karim, even by choosing him.
        Queue::fake();
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['payroll.attendance.process']);
        Role::where('name', 'like', 'Test role%')->latest('id')->firstOrFail()->update(['org_unit_id' => $factory->id]);
        Livewire::test(AttendanceReprocess::class)->set('employeeId', (string) $this->karim->id)->call('run')->assertHasErrors('employeeId');
        Livewire::test(AttendanceReprocess::class)->call('run')->assertHasNoErrors();
        $this->assertSame([$rahim->id], array_keys($this->queued()));
    }

    public function test_process_again_checks_the_range(): void
    {
        Queue::fake();

        Livewire::test(AttendanceReprocess::class)->set('to', '2026-12-11')->call('run')->assertHasErrors(['to' => 'before_or_equal']);
        Livewire::test(AttendanceReprocess::class)->set('from', '2026-12-05')->set('to', '2026-12-04')->call('run')->assertHasErrors(['to' => 'after_or_equal']);
        Livewire::test(AttendanceReprocess::class)->set('from', '2026-09-01')->call('run')->assertHasErrors('to');   // > 93 days
        Livewire::test(AttendanceReprocess::class)->set('from', '2026-09-08')->call('run')->assertHasNoErrors();      // exactly 93

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['payroll.punch.manage']);
        Livewire::test(AttendanceReprocess::class)->call('run')->assertForbidden();
    }

    public function test_process_again_really_rewrites_the_days(): void
    {
        // The queue runs at once in tests (sync), so this goes all the way to attendance_days.
        PunchLog::factory()->for($this->karim)->create(['punched_at' => '2026-12-08 09:00']);
        PunchLog::factory()->for($this->karim)->create(['punched_at' => '2026-12-08 18:00']);

        Livewire::test(AttendanceReprocess::class)->set('from', '2026-12-07')->set('to', '2026-12-08')->call('run')->assertHasNoErrors();

        $days = AttendanceDay::orderBy('date')->get();
        $this->assertSame([AttendanceStatus::Absent, AttendanceStatus::Present], $days->pluck('status')->all());
    }

    // ---- Triggers -----------------------------------------------------------------------------

    public function test_approving_a_roster_reprocesses_its_employees_and_those_of_the_roster_it_replaces(): void
    {
        $rahim = Employee::factory()->create(['join_date' => '2026-01-01']);
        $old = Roster::factory()->approved()->create(['from_date' => '2026-11-25', 'to_date' => '2026-12-05']);
        RosterItem::factory()->for($old)->create(['employee_id' => $rahim->id, 'date' => '2026-12-01']);

        $new = Roster::factory()->create(['from_date' => '2026-12-01', 'to_date' => '2026-12-20', 'replaces_roster_id' => $old->id]);
        RosterItem::factory()->for($new)->create(['employee_id' => $this->karim->id, 'date' => '2026-12-01']);

        $workflow = Workflow::create(['approval_type_id' => ApprovalType::forKey('roster')?->id, 'name' => 'Roster']);
        WorkflowStep::create(['workflow_id' => $workflow->id, 'level' => 1, 'approver_type' => ApproverType::Role, 'approver_role_id' => Role::administrator()?->id]);
        $approver = User::factory()->for($this->tenant)->create();
        Role::administrator()?->assignTo($approver);
        $new->submitForApproval(auth()->user());

        Queue::fake();
        app(WorkflowEngine::class)->respond(Approval::sole(), ApprovalStatus::Approved, $approver);

        // Both rosters' days up to today (the 11th–20th are in the future).
        $this->assertSame([
            $this->karim->id => [['2026-11-25', '2026-12-10']],
            $rahim->id => [['2026-11-25', '2026-12-10']],
        ], $this->queued());
    }

    public function test_saving_or_deleting_a_holiday_reprocesses_the_employees_of_its_calendars(): void
    {
        $head = HolidayProfile::factory()->create(['name' => 'Head office']);
        $plant = HolidayProfile::factory()->create(['name' => 'Plant']);
        $this->karim->update(['holiday_profile_id' => $head->id]);
        $rahim = Employee::factory()->create(['join_date' => '2026-01-01', 'holiday_profile_id' => $plant->id]);
        Employee::factory()->create(['join_date' => '2026-01-01']);   // no calendar: never affected

        // New holiday in the head office calendar only.
        Queue::fake();
        Livewire::test(HolidayForm::class)->call('create')
            ->set('form.date', '2026-12-03')->set('form.title', 'Strike day')->set('profileIds', [(string) $head->id])
            ->call('save')->assertHasNoErrors();
        $this->assertSame([$this->karim->id => [['2026-12-03', '2026-12-03']]], $this->queued());

        // Moved to another day and into the plant calendar: the old AND the new day, for both calendars.
        Queue::fake();
        $holiday = Holiday::sole();
        Livewire::test(HolidayForm::class)->call('edit', $holiday->id)
            ->set('form.date', '2026-12-04')->set('profileIds', [(string) $plant->id])
            ->call('save')->assertHasNoErrors();
        $jobs = $this->queued();
        $this->assertEqualsCanonicalizing([['2026-12-04', '2026-12-04'], ['2026-12-03', '2026-12-03']], $jobs[$this->karim->id]);
        $this->assertEqualsCanonicalizing([['2026-12-04', '2026-12-04'], ['2026-12-03', '2026-12-03']], $jobs[$rahim->id]);
        $this->assertCount(2, $jobs);

        // Deleted: its day becomes a normal day again for the plant.
        Queue::fake();
        Livewire::test(HolidayForm::class)->call('edit', $holiday->id)->call('delete');
        $this->assertModelMissing($holiday);
        $this->assertSame([$rahim->id => [['2026-12-04', '2026-12-04']]], $this->queued());

        // A future holiday changes no processed day.
        Queue::fake();
        Livewire::test(HolidayForm::class)->call('create')
            ->set('form.date', '2026-12-25')->set('form.title', 'Christmas')->set('profileIds', [(string) $head->id])
            ->call('save')->assertHasNoErrors();
        Queue::assertNothingPushed();
    }

    public function test_a_holiday_end_to_end_turns_an_absent_day_into_a_holiday(): void
    {
        $calendar = HolidayProfile::factory()->create();
        $this->karim->update(['holiday_profile_id' => $calendar->id]);
        AttendanceDay::factory()->for($this->karim)->create(['date' => '2026-12-03', 'status' => AttendanceStatus::Absent]);

        Livewire::test(HolidayForm::class)->call('create')
            ->set('form.date', '2026-12-03')->set('form.title', 'Strike day')->set('profileIds', [(string) $calendar->id])
            ->call('save')->assertHasNoErrors();

        $this->assertSame(AttendanceStatus::Holiday, AttendanceDay::sole()->status);
    }

    public function test_a_yearly_holiday_touches_this_year_and_last_year_up_to_today(): void
    {
        $yearly = new Holiday(['date' => '2020-02-21', 'repeats_yearly' => true]);
        $this->assertSame(['2025-02-21', '2026-02-21'], $yearly->pastDates('2026-12-10'));
        $this->assertSame(['2025-02-21'], $yearly->pastDates('2026-02-20'));

        $leap = new Holiday(['date' => '2024-02-29', 'repeats_yearly' => true]);
        $this->assertSame([], $leap->pastDates('2026-12-10'));    // 2025 and 2026 have no 29 February

        $once = new Holiday(['date' => '2026-12-25', 'repeats_yearly' => false]);
        $this->assertSame([], $once->pastDates('2026-12-10'));
        $this->assertSame(['2026-12-25'], $once->pastDates('2026-12-31'));
    }

    public function test_new_punches_reprocess_their_days_and_the_day_before_up_to_yesterday(): void
    {
        $rahim = Employee::factory()->create(['join_date' => '2026-01-01', 'access_code' => '2002']);
        Queue::fake();

        app(StorePunches::class)->handle([
            ['code' => '1001', 'punched_at' => Carbon::parse('2026-12-03 09:00')],
            ['code' => '1001', 'punched_at' => Carbon::parse('2026-12-05 18:00')],
            ['code' => '2002', 'punched_at' => Carbon::parse('2026-12-10 08:55')],   // this morning: only yesterday
            ['code' => '9999', 'punched_at' => Carbon::parse('2026-12-04 09:00')],   // nobody has this code (yet)
        ], PunchSource::Csv);

        $this->assertSame([
            $this->karim->id => [['2026-12-02', '2026-12-05']],
            $rahim->id => [['2026-12-09', '2026-12-09']],
        ], $this->queued());

        // Stored again (e.g. the next device sync): nothing new, nothing queued.
        Queue::fake();
        app(StorePunches::class)->handle([['code' => '1001', 'punched_at' => Carbon::parse('2026-12-03 09:00')]], PunchSource::Csv);
        Queue::assertNothingPushed();

        // HR gives someone the code 9999, then "Match unmatched punches": that day is processed.
        Queue::fake();
        $salma = Employee::factory()->create(['join_date' => '2026-01-01', 'access_code' => '9999']);
        $this->assertSame(1, PunchLog::linkEmployees());
        $this->assertSame([$salma->id => [['2026-12-03', '2026-12-04']]], $this->queued());
    }
}
