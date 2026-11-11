<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Enums\AttendanceStatus;
use App\Modules\Payroll\Enums\DayType;
use App\Modules\Payroll\Models\AttendanceDay;
use App\Modules\Payroll\Models\HolidayProfile;
use App\Modules\Payroll\Models\Shift;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The data behind the attendance calendars (7.8). [TA-08]
 *
 *   employeeMonth()  one employee, a month as weeks (Mon–Sun): the processed day if there is one, else what is
 *                    PLANNED (shift, weekend, holiday) — so upcoming days and holidays show too
 *   teamMonth()      many employees × the days of a month: processed days only (one query)
 */
class AttendanceCalendar
{
    public function __construct(private AttendanceInputs $inputs) {}

    /** "2026-12" → 1 December 2026; anything else → this month. */
    public static function month(?string $value): CarbonImmutable
    {
        return $value !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)
            ? CarbonImmutable::createFromFormat('!Y-m', $value)
            : CarbonImmutable::now()->startOfMonth();
    }

    /** Short letter per status for the team grid: P, ½, A, L, O, H. */
    public static function letter(AttendanceStatus $status): string
    {
        return match ($status) {
            AttendanceStatus::Present => 'P',
            AttendanceStatus::HalfDay => '½',
            AttendanceStatus::Absent => 'A',
            AttendanceStatus::Leave => 'L',
            AttendanceStatus::Off => 'O',
            AttendanceStatus::Holiday => 'H',
        };
    }

    /**
     * @return list<list<array{date: CarbonImmutable, inMonth: bool, isToday: bool, employed: bool, day: AttendanceDay|null,
     *                         plannedType: DayType|null, plannedShift: string|null, holiday: string|null}>>
     */
    public function employeeMonth(Employee $employee, CarbonImmutable $month): array
    {
        $first = $month->startOfMonth();
        $last = $month->endOfMonth()->startOfDay();
        $gridStart = $first->startOfWeek(CarbonImmutable::MONDAY);
        $gridEnd = $last->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay();

        $days = AttendanceDay::query()->with('shift:id,code')->where('employee_id', $employee->id)
            ->whereBetween('date', [$first->toDateString(), $last->toDateString()])
            ->get()->keyBy(fn (AttendanceDay $d) => $d->date->toDateString());

        // The plan for every day of the month (no database writes): day type and shift.
        $planned = collect($this->inputs->forEmployee($employee, $first, $last))->keyBy(fn ($in) => $in->date->toDateString());
        $codes = Shift::query()->pluck('code', 'id');
        $holidays = $employee->holiday_profile_id
            ? (HolidayProfile::with('holidays')->find($employee->holiday_profile_id)->holidays ?? collect())
            : collect();

        $joined = $employee->join_date->toDateString();
        $left = $employee->separation_date?->toDateString();
        $today = CarbonImmutable::parse(app(AttendanceReprocessor::class)->today());

        $weeks = [];
        for ($d = $gridStart; $d->lte($gridEnd); $d = $d->addDay()) {
            $key = $d->toDateString();
            $plan = $planned->get($key);
            $weeks[intdiv((int) $gridStart->diffInDays($d), 7)][] = [
                'date' => $d,
                'inMonth' => $d->month === $month->month,
                'isToday' => $d->isSameDay($today),
                'employed' => $key >= $joined && ($left === null || $key <= $left),
                'day' => $days->get($key),
                'plannedType' => $plan?->dayType,
                'plannedShift' => $plan?->shift?->shiftId ? ($codes[$plan->shift->shiftId] ?? null) : null,
                'holiday' => $holidays->first(fn ($h) => $h->fallsOn($d))?->title,
            ];
        }

        return array_values($weeks);
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return array<int, array<string, AttendanceDay>> employee id => [YYYY-MM-DD => processed day]
     */
    public function teamMonth(Collection $employees, CarbonImmutable $month): array
    {
        $matrix = array_fill_keys($employees->pluck('id')->all(), []);
        AttendanceDay::query()
            ->whereIn('employee_id', array_keys($matrix))
            ->whereBetween('date', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()])
            ->get(['employee_id', 'date', 'status', 'day_type', 'in_at', 'out_at', 'is_late', 'is_corrected', 'ot_minutes', 'ot_approved_minutes'])
            ->each(function (AttendanceDay $d) use (&$matrix) {
                $matrix[$d->employee_id][$d->date->toDateString()] = $d;
            });

        return $matrix;
    }

    /**
     * Totals of a month's processed days, for the summary line under a calendar.
     *
     * @param  iterable<AttendanceDay>  $days
     * @return array{present: int, absent: int, late: int, leave: int, overtime: int, approvedOvertime: int}
     */
    public static function totals(iterable $days): array
    {
        $t = ['present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0, 'overtime' => 0, 'approvedOvertime' => 0];
        foreach ($days as $d) {
            $t['present'] += in_array($d->status, [AttendanceStatus::Present, AttendanceStatus::HalfDay], true) ? 1 : 0;
            $t['absent'] += $d->status === AttendanceStatus::Absent ? 1 : 0;
            $t['late'] += $d->is_late ? 1 : 0;
            $t['leave'] += $d->status === AttendanceStatus::Leave ? 1 : 0;
            $t['overtime'] += $d->ot_minutes;
            $t['approvedOvertime'] += (int) $d->ot_approved_minutes;
        }

        return $t;
    }
}
