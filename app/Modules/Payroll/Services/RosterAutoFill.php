<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Actions\SaveRosterCells;
use App\Modules\Payroll\Models\Holiday;
use App\Modules\Payroll\Models\HolidayProfile;
use App\Modules\Payroll\Models\Roster;
use App\Modules\Payroll\Models\Shift;
use App\Modules\Payroll\Models\WeekendProfile;
use Illuminate\Support\Carbon;

/**
 * "Fill empty cells" in the roster grid (7.2c): a first draft from what is already set up (7.1),
 * which the planner then changes where needed. Only EMPTY cells are filled; planned ones stay.
 *
 *   holiday in the employee's holiday calendar   → off
 *   weekend in the employee's weekend profile    → off
 *   any other day                                → the employee's own shift, else the default shift
 *                                                  (none of both: the cell stays empty)
 */
class RosterAutoFill
{
    /**
     * @param  array<int|string, array<string, string>>  $cells  the grid as in Roster::cellMatrix()
     * @return array{0: array<int|string, array<string, string>>, 1: int} the filled grid and how many cells were filled
     */
    public function fill(Roster $roster, array $cells): array
    {
        $employees = $roster->gridEmployees()->keyBy('id');
        $default = Shift::default();
        $activeShiftIds = Shift::where('is_active', true)->pluck('id')->flip();
        $weekends = WeekendProfile::query()->whereIn('id', $employees->pluck('weekend_profile_id')->filter()->unique())->get()->keyBy('id');
        $holidayDates = $this->holidayDates($roster, $employees->pluck('holiday_profile_id')->filter()->unique()->all());

        $filled = 0;
        foreach ($cells as $employeeId => $row) {
            $employee = $employees->get((int) $employeeId);
            if (! $employee instanceof Employee) {
                continue;
            }
            $shiftId = $employee->shift_id !== null && $activeShiftIds->has($employee->shift_id) ? $employee->shift_id : $default?->id;
            $weekend = $employee->weekend_profile_id ? $weekends->get($employee->weekend_profile_id) : null;
            $holidays = $holidayDates[$employee->holiday_profile_id] ?? [];

            foreach ($row as $date => $value) {
                if ($value !== '') {
                    continue;
                }
                $day = Carbon::parse($date);
                $new = match (true) {
                    isset($holidays[$date]), $weekend?->isWeekend($day) === true => SaveRosterCells::OFF,
                    $shiftId !== null => (string) $shiftId,
                    default => '',
                };
                if ($new !== '') {
                    $cells[$employeeId][$date] = $new;
                    $filled++;
                }
            }
        }

        return [$cells, $filled];
    }

    /**
     * Per holiday calendar: the roster dates that are a holiday in it. Two queries in total, not one per cell.
     *
     * @param  list<int>  $profileIds
     * @return array<int, array<string, true>>
     */
    private function holidayDates(Roster $roster, array $profileIds): array
    {
        $out = [];
        $profiles = HolidayProfile::query()->whereIn('id', $profileIds)->with('holidays')->get();
        foreach ($profiles as $profile) {
            foreach ($roster->dates() as $day) {
                if ($profile->holidays->contains(fn (Holiday $h) => $h->fallsOn($day))) {
                    $out[$profile->id][$day->toDateString()] = true;
                }
            }
        }

        return $out;
    }
}
