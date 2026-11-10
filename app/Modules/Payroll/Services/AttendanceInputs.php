<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Contracts\LeaveCalendar;
use App\Modules\Payroll\Data\DayInput;
use App\Modules\Payroll\Data\ShiftTimes;
use App\Modules\Payroll\Enums\DayType;
use App\Modules\Payroll\Models\AttendanceDay;
use App\Modules\Payroll\Models\Holiday;
use App\Modules\Payroll\Models\HolidayProfile;
use App\Modules\Payroll\Models\PunchLog;
use App\Modules\Payroll\Models\RosterItem;
use App\Modules\Payroll\Models\Shift;
use App\Modules\Payroll\Models\WeekendProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Collects from the database what AttendanceProcessor needs, for one employee and a range of days. [TA-05]
 * A handful of queries for the whole range (not one per day).
 *
 *   shift      approved roster cell (its own times if set) → the employee's own shift → the default shift
 *   day type   holiday in their calendar (festival if marked) — always wins
 *              → roster cell: off / on call = Weekend, a shift = Workday (even on a weekend-profile day)
 *              → weekend profile → Workday
 *   leave      LeaveCalendar (Phase 8)
 *   punches    those inside the day's window (AttendanceProcessor::windowFor)
 *
 * The monthly grace count is NOT set here (it depends on the results of the days before):
 * AttendanceService keeps it, starting from graceUsedBefore().
 */
class AttendanceInputs
{
    public function __construct(private LeaveCalendar $leave) {}

    /**
     * @return list<DayInput> one per day, in date order (the caller clips the range to the employment period)
     */
    public function forEmployee(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $dates = [];
        for ($d = $from; $d->lte($to); $d = $d->addDay()) {
            $dates[] = $d;
        }
        if ($dates === []) {
            return [];
        }

        // Roster cells of APPROVED rosters, one per date (newest approval wins, as RosterItem::planFor). ±1 day for the windows.
        $cells = RosterItem::query()
            ->join('rosters', 'rosters.id', '=', 'roster_items.roster_id')
            ->whereNotNull('rosters.approved_at')
            ->where('roster_items.employee_id', $employee->id)
            ->whereBetween('roster_items.date', [$from->subDay()->toDateString(), $to->addDay()->toDateString()])
            ->orderByDesc('rosters.approved_at')->orderByDesc('rosters.id')
            ->select('roster_items.*')
            ->get()
            ->unique(fn (RosterItem $i) => $i->date->toDateString())
            ->keyBy(fn (RosterItem $i) => $i->date->toDateString());

        $shifts = Shift::query()->whereIn('id', array_filter([...$cells->pluck('shift_id')->all(), $employee->shift_id]))->get()->keyBy('id');
        $default = Shift::default();
        $own = $employee->shift_id !== null ? ($shifts[$employee->shift_id] ?? null) : null;
        $weekend = $employee->weekend_profile_id ? WeekendProfile::find($employee->weekend_profile_id) : null;
        $holidays = $employee->holiday_profile_id
            ? (HolidayProfile::with('holidays')->find($employee->holiday_profile_id)->holidays ?? collect())
            : collect();
        $leave = $this->leave->leaveDates($employee, $from, $to);

        $shiftFor = function (CarbonImmutable $date) use ($cells, $shifts, $own, $default): ?ShiftTimes {
            $cell = $cells->get($date->toDateString());
            if ($cell?->shift_id !== null && isset($shifts[$cell->shift_id])) {
                return ShiftTimes::fromShift($shifts[$cell->shift_id], $cell);
            }
            $shift = $own ?? $default;

            return $shift ? ShiftTimes::fromShift($shift) : null;
        };

        // All punches that can fall in any window of the range, in one query.
        $punches = PunchLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('punched_at', [$from->subDay(), $to->addDays(2)])
            ->orderBy('punched_at')
            ->pluck('punched_at')
            ->map(fn ($p) => CarbonImmutable::parse($p))
            ->all();

        $inputs = [];
        foreach ($dates as $date) {
            $shift = $shiftFor($date);
            [$windowFrom, $windowUntil] = AttendanceProcessor::windowFor($date, $shiftFor($date->subDay()), $shift, $shiftFor($date->addDay()));

            $input = new DayInput(
                date: $date,
                dayType: $this->dayType($date, $cells->get($date->toDateString()), $weekend, $holidays),
                shift: $shift,
                punches: array_values(array_filter($punches, fn (CarbonImmutable $p) => $p->gte($windowFrom) && $p->lt($windowUntil))),
                onLeave: isset($leave[$date->toDateString()]),
            );
            $inputs[] = $input;
        }

        return $inputs;
    }

    /** How often grace was used in $from's month BEFORE $from (the start of the running count). */
    public function graceUsedBefore(Employee $employee, CarbonImmutable $from): int
    {
        if ($from->day === 1) {
            return 0;
        }

        return AttendanceDay::query()->where('employee_id', $employee->id)->where('used_grace', true)
            ->whereBetween('date', [$from->startOfMonth()->toDateString(), $from->subDay()->toDateString()])
            ->count();
    }

    /**
     * @param  Collection<int, Holiday>  $holidays
     */
    private function dayType(CarbonImmutable $date, ?RosterItem $cell, ?WeekendProfile $weekend, $holidays): DayType
    {
        $holiday = $holidays->first(fn (Holiday $h) => $h->fallsOn($date));
        if ($holiday !== null) {
            return $holiday->is_festival ? DayType::Festival : DayType::Holiday;
        }
        if ($cell !== null) {
            return $cell->is_off || ($cell->is_on_call && $cell->shift_id === null) ? DayType::Weekend : DayType::Workday;
        }

        return $weekend?->isWeekend($date) ? DayType::Weekend : DayType::Workday;
    }
}
