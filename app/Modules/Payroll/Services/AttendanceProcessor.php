<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Payroll\Data\DayInput;
use App\Modules\Payroll\Data\DayResult;
use App\Modules\Payroll\Data\ShiftTimes;
use App\Modules\Payroll\Enums\AttendanceStatus;
use App\Modules\Payroll\Enums\DayType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The attendance rules: ONE working day in, its result out. Pure logic (no database, no clock), so every
 * case is unit tested (tests/Unit/AttendanceProcessorTest). [TA-05] Architecture §10.
 *
 *   on leave                          → Leave (punches still recorded)
 *   weekend / holiday, no work        → Off / Holiday
 *   weekend / holiday, worked         → Present; ALL worked time is overtime (rate by day type in payroll)
 *   workday, no punches               → Absent
 *   workday, one punch                → Present with a note: in or out is missing (fix with a correction, 7.6)
 *   workday, in + out                 → Present, or Half day by the shift's half-day rules; plus:
 *       late          in after the start. Within grace = not late (but grace used, monthly limit);
 *                     within flexibility = not late at all, the end moves by as much
 *       early exit    out before the (moved) end
 *       overtime      pre = before the start, post = after the (moved) end
 *
 * In = first punch, out = last punch of the day's window (windowFor()); punches in between are ignored.
 */
class AttendanceProcessor
{
    /** The next working day's punches start this long before its shift start … */
    public const WINDOW_BEFORE_MINUTES = 240;

    /** … but never before this long after the previous shift's end (its late clock-out stays with it). */
    public const AFTER_END_MINUTES = 120;

    public function process(DayInput $in): DayResult
    {
        $punches = $in->punches;
        $count = count($punches);
        $first = $punches[0] ?? null;
        $last = $count >= 2 ? $punches[$count - 1] : null;
        $worked = $first && $last ? self::minutes($first, $last) : 0;

        // The fields every result has: what kind of day, which shift, and the punches.
        $simple = fn (AttendanceStatus $status, ?string $note = null, int $ot = 0) => new DayResult(
            status: $status, dayType: $in->dayType, shiftId: $in->shift?->shiftId,
            inAt: $first, outAt: $last, punchCount: $count, workedMinutes: $worked, otMinutes: $ot, note: $note,
        );

        if ($in->onLeave) {
            return $simple(AttendanceStatus::Leave);
        }

        if ($in->dayType !== DayType::Workday) {
            $offStatus = $in->dayType === DayType::Weekend ? AttendanceStatus::Off : AttendanceStatus::Holiday;

            return $last === null
                ? $simple($offStatus, $count === 1 ? __('Single punch on a day off.') : null)
                : $simple(AttendanceStatus::Present, ot: $worked);   // all of it is overtime
        }

        if ($count === 0) {
            return $simple(AttendanceStatus::Absent);
        }
        if ($last === null) {
            return $simple(AttendanceStatus::Present, __('Single punch: in or out is missing.'));
        }
        if ($in->shift === null) {
            return $simple(AttendanceStatus::Present, __('No shift: late, early exit and overtime not checked.'));
        }

        return $this->workday($in, $in->shift, $first, $last, $worked);
    }

    private function workday(DayInput $in, ShiftTimes $shift, CarbonImmutable $first, CarbonImmutable $last, int $worked): DayResult
    {
        $start = $shift->startOn($in->date);
        $end = $shift->endOn($in->date);

        // Late: after the start. Within flexibility → not late, but the expected end moves by as much.
        $lateBy = max(0, self::minutes($start, $first));
        if ($lateBy > 0 && $lateBy <= $shift->flexibilityMinutes) {
            $end = $end->addMinutes($lateBy);
            $lateBy = 0;
        }
        $graceLeft = $shift->graceLimitPerMonth === null || $in->graceUsedThisMonth < $shift->graceLimitPerMonth;
        $withinGrace = $lateBy > 0 && $lateBy <= $shift->graceMinutes && $graceLeft;

        $otPre = max(0, self::minutes($first, $start));
        $otPost = max(0, self::minutes($end, $last));

        $halfAfter = $shift->halfDayAfterOn($in->date);
        $halfDay = ($halfAfter !== null && $first->greaterThan($halfAfter))
            || ($shift->halfDayMinutes !== null && $worked < $shift->halfDayMinutes);

        return new DayResult(
            status: $halfDay ? AttendanceStatus::HalfDay : AttendanceStatus::Present,
            dayType: $in->dayType,
            shiftId: $shift->shiftId,
            inAt: $first,
            outAt: $last,
            punchCount: count($in->punches),
            workedMinutes: $worked,
            lateMinutes: $lateBy,
            isLate: $lateBy > 0 && ! $withinGrace,
            usedGrace: $withinGrace,
            earlyExitMinutes: max(0, self::minutes($last, $end)),
            otPreMinutes: $otPre,
            otPostMinutes: $otPost,
            otMinutes: $otPre + $otPost,
        );
    }

    /**
     * The punches that belong to working day $date: [from, until), until exclusive.
     * Each edge is a boundary() shared with the neighbouring day, so a punch never counts twice or for nobody.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function windowFor(CarbonInterface $date, ?ShiftTimes $yesterday, ?ShiftTimes $today, ?ShiftTimes $tomorrow): array
    {
        $date = CarbonImmutable::parse($date->toDateString());

        return [self::boundary($date->subDay(), $yesterday, $today), self::boundary($date, $today, $tomorrow)];
    }

    /**
     * Where working day $date ends and the next one begins: the LATER of
     *   - the next shift's start − WINDOW_BEFORE (midnight when there is no next shift), and
     *   - this shift's end + AFTER_END (so a night shift's 06:10 clock-out is not taken by the next day).
     */
    public static function boundary(CarbonImmutable $date, ?ShiftTimes $shift, ?ShiftTimes $next): CarbonImmutable
    {
        $nextDay = $date->addDay();
        $nextFrom = $next ? $next->startOn($nextDay)->subMinutes(self::WINDOW_BEFORE_MINUTES) : $nextDay->startOfDay();
        $guard = $shift?->endOn($date)->addMinutes(self::AFTER_END_MINUTES);

        return $guard !== null && $guard->greaterThan($nextFrom) ? $guard : $nextFrom;
    }

    /** Whole minutes from $a to $b (negative when $b is earlier). Carbon's diffInMinutes() gives a signed float. */
    private static function minutes(CarbonInterface $a, CarbonInterface $b): int
    {
        return intdiv($b->getTimestamp() - $a->getTimestamp(), 60);
    }
}
