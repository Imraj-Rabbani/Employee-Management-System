<?php

namespace Tests\Unit;

use App\Modules\Payroll\Data\DayInput;
use App\Modules\Payroll\Data\DayResult;
use App\Modules\Payroll\Data\ShiftTimes;
use App\Modules\Payroll\Enums\AttendanceStatus;
use App\Modules\Payroll\Enums\DayType;
use App\Modules\Payroll\Services\AttendanceProcessor;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Task 7.4: the attendance rules, one case per test. No database: inputs in, result out. [TA-05]
 * Laravel's TestCase only for __() (translated notes).
 *
 * General shift: 09:00–18:00, 15 min grace. Day: Tuesday 1 December 2026.
 */
class AttendanceProcessorTest extends TestCase
{
    private function general(array $rules = []): ShiftTimes
    {
        return new ShiftTimes(...['start' => '09:00', 'end' => '18:00', 'graceMinutes' => 15, 'shiftId' => 7, ...$rules]);
    }

    /** @param  list<string>  $times  "08:55" (on 1 Dec) or "2026-12-02 06:00" */
    private function day(array $times, ?ShiftTimes $shift = null, DayType $type = DayType::Workday, bool $onLeave = false, int $graceUsed = 0, string $date = '2026-12-01'): DayResult
    {
        $punches = array_map(fn ($t) => CarbonImmutable::parse(strlen($t) === 5 ? "{$date} {$t}" : $t), $times);

        return (new AttendanceProcessor)->process(new DayInput(
            date: CarbonImmutable::parse($date),
            dayType: $type,
            shift: $shift ?? $this->general(),
            punches: $punches,
            onLeave: $onLeave,
            graceUsedThisMonth: $graceUsed,
        ));
    }

    public function test_regular_day(): void
    {
        $r = $this->day(['09:00', '18:00']);

        $this->assertSame(AttendanceStatus::Present, $r->status);
        $this->assertSame(540, $r->workedMinutes);
        $this->assertSame([0, false, false, 0, 0], [$r->lateMinutes, $r->isLate, $r->usedGrace, $r->earlyExitMinutes, $r->otMinutes]);
        $this->assertSame(7, $r->shiftId);
        $this->assertSame('2026-12-01 18:00', $r->outAt?->format('Y-m-d H:i'));
    }

    public function test_late_within_grace_is_not_late_but_uses_grace(): void
    {
        $r = $this->day(['09:10', '18:00']);

        $this->assertSame([10, false, true], [$r->lateMinutes, $r->isLate, $r->usedGrace]);
        $this->assertSame(AttendanceStatus::Present, $r->status);
    }

    public function test_late_over_grace(): void
    {
        $r = $this->day(['09:20', '18:00']);

        $this->assertSame([20, true, false], [$r->lateMinutes, $r->isLate, $r->usedGrace]);
    }

    public function test_grace_runs_out_after_the_monthly_limit(): void
    {
        $shift = $this->general(['graceLimitPerMonth' => 3]);

        $this->assertTrue($this->day(['09:10', '18:00'], $shift, graceUsed: 2)->usedGrace);   // 3rd time: still fine
        $r = $this->day(['09:10', '18:00'], $shift, graceUsed: 3);                           // 4th time: late
        $this->assertSame([true, false], [$r->isLate, $r->usedGrace]);
    }

    public function test_flexibility_moves_the_end_instead_of_being_late(): void
    {
        $shift = $this->general(['flexibilityMinutes' => 60]);

        $r = $this->day(['09:40', '18:30'], $shift);   // 40 min later in, expected out 18:40
        $this->assertSame([0, false, 10], [$r->lateMinutes, $r->isLate, $r->earlyExitMinutes]);

        $r = $this->day(['10:30', '19:30'], $shift);   // beyond the flexibility: late as usual
        $this->assertSame([90, true], [$r->lateMinutes, $r->isLate]);
    }

    public function test_early_exit(): void
    {
        $r = $this->day(['09:00', '17:15']);

        $this->assertSame(45, $r->earlyExitMinutes);
        $this->assertSame(AttendanceStatus::Present, $r->status);
    }

    public function test_overnight_shift(): void
    {
        $night = new ShiftTimes(start: '22:00', end: '06:00', graceMinutes: 10);

        $r = $this->day(['22:05', '2026-12-02 06:30'], $night);

        $this->assertSame(AttendanceStatus::Present, $r->status);
        $this->assertSame(505, $r->workedMinutes);
        $this->assertSame([5, true], [$r->lateMinutes, $r->usedGrace]);
        $this->assertSame([0, 30], [$r->earlyExitMinutes, $r->otPostMinutes]);
    }

    public function test_half_day_when_too_late_or_too_short(): void
    {
        $shift = $this->general(['halfDayAfter' => '13:00', 'halfDayMinutes' => 240]);

        $this->assertSame(AttendanceStatus::HalfDay, $this->day(['13:30', '18:00'], $shift)->status);   // came after 13:00
        $this->assertSame(AttendanceStatus::HalfDay, $this->day(['09:00', '12:00'], $shift)->status);   // 3 h < 4 h
        $this->assertSame(AttendanceStatus::Present, $this->day(['09:00', '13:00'], $shift)->status);   // exactly 4 h
    }

    public function test_half_day_after_time_for_an_overnight_shift_is_the_next_morning(): void
    {
        $night = new ShiftTimes(start: '22:00', end: '06:00', halfDayAfter: '01:00');

        $this->assertSame(AttendanceStatus::Present, $this->day(['23:30', '2026-12-02 06:00'], $night)->status);
        $this->assertSame(AttendanceStatus::HalfDay, $this->day(['2026-12-02 01:30', '2026-12-02 06:00'], $night)->status);
    }

    public function test_absent(): void
    {
        $r = $this->day([]);

        $this->assertSame(AttendanceStatus::Absent, $r->status);
        $this->assertNull($r->inAt);
    }

    public function test_weekend_off_and_weekend_worked(): void
    {
        $this->assertSame(AttendanceStatus::Off, $this->day([], type: DayType::Weekend)->status);

        $r = $this->day(['10:00', '14:30'], type: DayType::Weekend);
        $this->assertSame(AttendanceStatus::Present, $r->status);
        $this->assertSame([270, 270, 0], [$r->workedMinutes, $r->otMinutes, $r->lateMinutes]);   // all overtime, never late
        $this->assertSame(DayType::Weekend, $r->dayType);
    }

    public function test_holiday_and_festival(): void
    {
        $this->assertSame(AttendanceStatus::Holiday, $this->day([], type: DayType::Holiday)->status);

        $r = $this->day(['09:00', '12:00'], type: DayType::Festival);
        $this->assertSame([AttendanceStatus::Present, DayType::Festival, 180], [$r->status, $r->dayType, $r->otMinutes]);

        $this->assertSame(AttendanceStatus::Holiday, $this->day(['09:00'], type: DayType::Holiday)->status);   // single punch: not worked
    }

    public function test_leave_wins_but_punches_are_kept(): void
    {
        $this->assertSame(AttendanceStatus::Leave, $this->day([], onLeave: true)->status);

        $r = $this->day(['09:00', '11:00'], onLeave: true);
        $this->assertSame([AttendanceStatus::Leave, 120, 0], [$r->status, $r->workedMinutes, $r->otMinutes]);
    }

    public function test_overtime_before_and_after_the_shift(): void
    {
        $r = $this->day(['07:30', '20:15']);

        $this->assertSame([90, 135, 225], [$r->otPreMinutes, $r->otPostMinutes, $r->otMinutes]);
        $this->assertSame([0, 0], [$r->lateMinutes, $r->earlyExitMinutes]);
    }

    public function test_one_punch_is_present_with_a_note_and_middle_punches_are_ignored(): void
    {
        $r = $this->day(['09:02']);
        $this->assertSame([AttendanceStatus::Present, 0, 1], [$r->status, $r->workedMinutes, $r->punchCount]);
        $this->assertStringContainsString('missing', (string) $r->note);

        $r = $this->day(['08:58', '13:00', '14:00', '18:05']);   // lunch punches in between
        $this->assertSame([4, 547, 5], [$r->punchCount, $r->workedMinutes, $r->otPostMinutes]);
    }

    public function test_a_workday_without_any_shift_counts_worked_time_only(): void
    {
        $r = (new AttendanceProcessor)->process(new DayInput(
            date: CarbonImmutable::parse('2026-12-01'), dayType: DayType::Workday, shift: null,
            punches: [CarbonImmutable::parse('2026-12-01 10:00'), CarbonImmutable::parse('2026-12-01 16:00')],
        ));

        $this->assertSame([AttendanceStatus::Present, 360, 0, null], [$r->status, $r->workedMinutes, $r->lateMinutes, $r->shiftId]);
    }

    public function test_the_punch_window_of_a_day(): void
    {
        $date = CarbonImmutable::parse('2026-12-01');
        $general = $this->general();
        $night = new ShiftTimes('22:00', '06:00');
        $fmt = fn (array $w) => [$w[0]->format('Y-m-d H:i'), $w[1]->format('Y-m-d H:i')];

        // Day shifts every day: 05:00 to 05:00.
        $this->assertSame(['2026-12-01 05:00', '2026-12-02 05:00'], $fmt(AttendanceProcessor::windowFor($date, $general, $general, $general)));

        // Night shift (from 18:00 = start − 4 h), then a day shift: the night's 06:xx clock-out stays with it
        // (until 08:00 = end + 2 h) …
        $this->assertSame(['2026-12-01 18:00', '2026-12-02 08:00'], $fmt(AttendanceProcessor::windowFor($date, $general, $night, $general)));
        // … and the next day starts exactly there: nothing is counted twice.
        $this->assertSame('2026-12-02 08:00', AttendanceProcessor::windowFor($date->addDay(), $night, $general, $general)[0]->format('Y-m-d H:i'));

        // No shifts at all: the calendar day.
        $this->assertSame(['2026-12-01 00:00', '2026-12-02 00:00'], $fmt(AttendanceProcessor::windowFor($date, null, null, null)));
    }

    // ---- Corrections (7.6) --------------------------------------------------------------------

    /** @param  list<string>  $punches  "08:55" on 1 Dec */
    private function corrected(array $punches, ?string $in, ?string $out, DayType $type = DayType::Workday): DayResult
    {
        $at = fn (?string $t) => $t === null ? null : CarbonImmutable::parse(strlen($t) === 5 ? "2026-12-01 {$t}" : $t);

        return (new AttendanceProcessor)->process(new DayInput(
            date: CarbonImmutable::parse('2026-12-01'),
            dayType: $type,
            shift: $this->general(),
            punches: array_map($at, $punches),
            correctedIn: $at($in),
            correctedOut: $at($out),
        ));
    }

    public function test_a_correction_turns_an_absent_day_into_a_regular_day(): void
    {
        $r = $this->corrected([], '09:00', '18:00');

        $this->assertSame([AttendanceStatus::Present, 540, 0, true], [$r->status, $r->workedMinutes, $r->punchCount, $r->isCorrected]);
        $this->assertFalse($this->day([])->isCorrected);
    }

    public function test_a_correction_of_one_end_keeps_the_punch_of_the_other(): void
    {
        // Forgot to punch out: the single 09:20 punch stays the "in" (late), the correction gives the "out".
        $r = $this->corrected(['09:20'], null, '18:00');
        $this->assertSame(['09:20', '18:00', 20, true, null], [$r->inAt?->format('H:i'), $r->outAt?->format('H:i'), $r->lateMinutes, $r->isLate, $r->note]);

        // A wrong "in" replaced; the last punch stays the "out".
        $r = $this->corrected(['10:30', '13:00', '18:00'], '09:00', null);
        $this->assertSame(['09:00', '18:00', false], [$r->inAt?->format('H:i'), $r->outAt?->format('H:i'), $r->isLate]);

        // The night's out on the next morning.
        $r = $this->corrected(['22:00'], null, '2026-12-02 06:00');
        $this->assertSame(['2026-12-01 22:00', '2026-12-02 06:00', 480], [$r->inAt?->format('Y-m-d H:i'), $r->outAt?->format('Y-m-d H:i'), $r->workedMinutes]);
    }

    public function test_a_correction_that_leaves_only_one_time_is_still_a_single_punch(): void
    {
        // Corrected in at 09:00, the only punch is earlier: no out.
        $r = $this->corrected(['08:50'], '09:00', null);

        $this->assertSame([AttendanceStatus::Present, '09:00', null], [$r->status, $r->inAt?->format('H:i'), $r->outAt]);
        $this->assertSame('Single punch: in or out is missing.', $r->note);
    }

    public function test_a_corrected_weekend_day_is_all_overtime(): void
    {
        $r = $this->corrected([], '10:00', '14:00', DayType::Weekend);

        $this->assertSame([AttendanceStatus::Present, 240, true], [$r->status, $r->otMinutes, $r->isCorrected]);
    }
}
