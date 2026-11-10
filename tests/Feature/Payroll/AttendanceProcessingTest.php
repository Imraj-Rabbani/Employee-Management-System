<?php

namespace Tests\Feature\Payroll;

use App\Models\Tenant;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Contracts\LeaveCalendar;
use App\Modules\Payroll\Enums\AttendanceStatus;
use App\Modules\Payroll\Enums\DayType;
use App\Modules\Payroll\Enums\Weekday;
use App\Modules\Payroll\Jobs\ProcessAttendance;
use App\Modules\Payroll\Models\AttendanceDay;
use App\Modules\Payroll\Models\Holiday;
use App\Modules\Payroll\Models\HolidayProfile;
use App\Modules\Payroll\Models\PunchLog;
use App\Modules\Payroll\Models\Roster;
use App\Modules\Payroll\Models\RosterItem;
use App\Modules\Payroll\Models\Shift;
use App\Modules\Payroll\Models\WeekendProfile;
use App\Modules\Payroll\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 7.4c: from the database to attendance_days (inputs, saving, job, command, schedule). [TA-05]
 * The rules themselves are unit tested in tests/Unit/AttendanceProcessorTest.
 *
 * December 2026: Tue 1, Wed 2, Thu 3, Fri 4, Sat 5. Today = 10 December.
 */
class AttendanceProcessingTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private Employee $karim;

    private Shift $general;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-10 12:00');
        $this->tenant = $this->createMigratedTenant(['modules' => ['hrm', 'payroll', 'workflow']]);
        $this->actingAsTenantUser(tenant: $this->tenant);

        $this->general = Shift::factory()->create(['code' => 'G', 'start_time' => '09:00', 'end_time' => '18:00', 'grace_minutes' => 15, 'is_default' => true]);
        $this->karim = Employee::factory()->create([
            'join_date' => '2026-01-01',
            'weekend_profile_id' => WeekendProfile::factory()->create(['day_one' => Weekday::Friday, 'day_two' => Weekday::Saturday])->id,
        ]);
    }

    private function punch(string ...$times): void
    {
        foreach ($times as $t) {
            PunchLog::factory()->for($this->karim)->create(['punched_at' => $t]);
        }
    }

    private function processDays(string $from, ?string $to = null): int
    {
        return app(AttendanceService::class)->process($this->karim->fresh(), CarbonImmutable::parse($from), CarbonImmutable::parse($to ?? $from));
    }

    private function dayOf(string $date): AttendanceDay
    {
        return AttendanceDay::where('employee_id', $this->karim->id)->whereDate('date', $date)->sole();
    }

    public function test_a_week_from_punches_weekend_and_default_shift(): void
    {
        $this->punch('2026-12-01 08:58', '2026-12-01 18:03');   // Tue: on time
        $this->punch('2026-12-02 09:25', '2026-12-02 18:00');   // Wed: late
        // Thu: nothing → absent; Fri / Sat: weekend
        $this->punch('2026-12-05 10:00', '2026-12-05 13:00');   // Sat: worked on the weekend

        $this->assertSame(5, $this->processDays('2026-12-01', '2026-12-05'));

        $this->assertSame([AttendanceStatus::Present, $this->general->id], [$this->dayOf('2026-12-01')->status, $this->dayOf('2026-12-01')->shift_id]);
        $this->assertTrue($this->dayOf('2026-12-02')->is_late);
        $this->assertSame(AttendanceStatus::Absent, $this->dayOf('2026-12-03')->status);
        $this->assertSame([AttendanceStatus::Off, DayType::Weekend], [$this->dayOf('2026-12-04')->status, $this->dayOf('2026-12-04')->day_type]);
        $this->assertSame([AttendanceStatus::Present, 180], [$this->dayOf('2026-12-05')->status, $this->dayOf('2026-12-05')->ot_minutes]);

        // Running again rewrites the same rows (one per day), e.g. after a late punch upload.
        $this->punch('2026-12-03 09:00', '2026-12-03 18:00');
        $this->processDays('2026-12-01', '2026-12-05');
        $this->assertSame(5, AttendanceDay::count());
        $this->assertSame(AttendanceStatus::Present, $this->dayOf('2026-12-03')->status);
    }

    public function test_holidays_win_and_festivals_are_their_own_day_type(): void
    {
        $calendar = HolidayProfile::factory()->create();
        $calendar->holidays()->attach([
            Holiday::factory()->create(['date' => '2025-12-01', 'title' => 'Yearly', 'repeats_yearly' => true])->id,
            Holiday::factory()->create(['date' => '2026-12-02', 'title' => 'Eid', 'is_festival' => true])->id,
            Holiday::factory()->create(['date' => '2026-12-04', 'title' => 'On a Friday'])->id,
        ]);
        $this->karim->update(['holiday_profile_id' => $calendar->id]);

        $this->processDays('2026-12-01', '2026-12-04');

        $this->assertSame([AttendanceStatus::Holiday, DayType::Holiday], [$this->dayOf('2026-12-01')->status, $this->dayOf('2026-12-01')->day_type]);
        $this->assertSame(DayType::Festival, $this->dayOf('2026-12-02')->day_type);
        $this->assertSame(DayType::Holiday, $this->dayOf('2026-12-04')->day_type);   // holiday beats weekend
    }

    public function test_an_approved_roster_decides_shift_and_days_off(): void
    {
        $night = Shift::factory()->create(['code' => 'N', 'start_time' => '22:00', 'end_time' => '06:00']);
        $roster = Roster::factory()->approved()->create(['from_date' => '2026-12-01', 'to_date' => '2026-12-05']);
        $cell = fn (string $date, array $values) => RosterItem::factory()->for($roster)->create(['employee_id' => $this->karim->id, 'date' => $date, 'shift_id' => null, ...$values]);
        $cell('2026-12-01', ['shift_id' => $night->id]);                                  // night shift
        $cell('2026-12-02', ['is_off' => true]);                                          // off on a Wednesday
        $cell('2026-12-04', ['shift_id' => $this->general->id]);                          // works on Friday
        $cell('2026-12-05', ['shift_id' => $this->general->id, 'start_time' => '10:00', 'end_time' => '15:00']);   // own times
        Roster::factory()->create()->items()->create(['employee_id' => $this->karim->id, 'date' => '2026-12-03', 'is_off' => true]);   // a DRAFT: ignored

        $this->punch('2026-12-01 21:55', '2026-12-02 06:10');   // the night shift, over midnight
        $this->punch('2026-12-05 10:20', '2026-12-05 15:00');

        $this->processDays('2026-12-01', '2026-12-05');

        $night1 = $this->dayOf('2026-12-01');
        $this->assertSame([AttendanceStatus::Present, $night->id, 2, 10], [$night1->status, $night1->shift_id, $night1->punch_count, $night1->ot_post_minutes]);
        $this->assertSame([AttendanceStatus::Off, 0], [$this->dayOf('2026-12-02')->status, $this->dayOf('2026-12-02')->punch_count]);   // 06:10 is not Wednesday's
        $this->assertSame(AttendanceStatus::Absent, $this->dayOf('2026-12-03')->status);   // draft roster does not count
        $this->assertSame([DayType::Workday, AttendanceStatus::Absent], [$this->dayOf('2026-12-04')->day_type, $this->dayOf('2026-12-04')->status]);
        $this->assertSame([20, true], [$this->dayOf('2026-12-05')->late_minutes, $this->dayOf('2026-12-05')->is_late]);   // late against 10:00
    }

    public function test_the_monthly_grace_limit_counts_across_days_and_restarts_each_month(): void
    {
        $this->general->update(['grace_limit_per_month' => 1]);
        $this->punch('2026-12-01 09:10', '2026-12-01 18:00', '2026-12-02 09:10', '2026-12-02 18:00');

        $this->processDays('2026-12-01');                 // first: grace
        $this->processDays('2026-12-02');                 // second, run on its own: the count comes from the database
        $this->assertTrue($this->dayOf('2026-12-01')->used_grace);
        $this->assertTrue($this->dayOf('2026-12-02')->is_late);

        $this->punch('2026-11-30 09:10', '2026-11-30 18:00');
        $this->processDays('2026-11-30');
        $this->assertTrue($this->dayOf('2026-11-30')->used_grace);   // November has its own limit
    }

    public function test_leave_comes_from_the_leave_calendar(): void
    {
        $this->app->instance(LeaveCalendar::class, new class implements LeaveCalendar
        {
            public function leaveDates(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array
            {
                return ['2026-12-03' => true];
            }
        });

        $this->processDays('2026-12-03');

        $this->assertSame(AttendanceStatus::Leave, $this->dayOf('2026-12-03')->status);
    }

    public function test_locked_days_before_joining_after_leaving_and_the_future_are_left_alone(): void
    {
        AttendanceDay::factory()->for($this->karim)->create(['date' => '2026-12-01', 'status' => AttendanceStatus::Present])
            ->forceFill(['is_locked' => true])->save();
        $this->karim->update(['join_date' => '2026-12-01', 'separation_date' => '2026-12-12']);

        $written = $this->processDays('2026-11-28', '2026-12-31');

        $this->assertSame(AttendanceStatus::Present, $this->dayOf('2026-12-01')->status);   // locked: not turned into Absent
        $this->assertSame(9, $written);                                                     // 2–10 Dec (today), not 11–12
        $this->assertSame('2026-12-02', AttendanceDay::orderBy('date')->where('is_locked', false)->first()->date->toDateString());
        $this->assertSame('2026-12-10', AttendanceDay::orderByDesc('date')->first()->date->toDateString());
    }

    public function test_the_command_queues_one_job_per_employee_for_yesterday(): void
    {
        Queue::fake();
        Employee::factory()->create(['join_date' => '2027-01-01']);   // not employed yet
        $left = Employee::factory()->create(['join_date' => '2020-01-01', 'separation_date' => '2026-12-09']);

        $this->assertSame(0, Artisan::call('payroll:process-attendance'));

        Queue::assertPushed(ProcessAttendance::class, 2);   // Karim + the one who left on 9 Dec (= yesterday)
        Queue::assertPushed(ProcessAttendance::class, fn (ProcessAttendance $job) => $job->employeeId === $left->id
            && $job->from === '2026-12-09' && $job->to === '2026-12-09' && $job->tenantId === $this->tenant->id);

        $this->assertSame(1, Artisan::call('payroll:process-attendance', ['--from' => '2026-12-05', '--to' => '2026-12-01']));
    }

    public function test_the_command_can_run_right_away_for_one_employee(): void
    {
        $this->punch('2026-12-01 09:00', '2026-12-01 18:00');

        Artisan::call('payroll:process-attendance', ['--date' => '2026-12-01', '--employee' => [$this->karim->id], '--sync' => true]);

        $this->assertSame(AttendanceStatus::Present, $this->dayOf('2026-12-01')->status);
        $this->assertSame(1, AttendanceDay::count());
    }

    public function test_it_runs_every_night(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command ?? '', 'tenants:run payroll:process-attendance'));

        $this->assertNotNull($event);
        $this->assertSame('0 1 * * *', $event->expression);
    }
}
