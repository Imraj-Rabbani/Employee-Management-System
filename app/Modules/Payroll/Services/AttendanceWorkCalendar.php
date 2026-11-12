<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Hrm\Contracts\WorkCalendar;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Enums\DayType;
use Carbon\CarbonImmutable;

/**
 * Days off for leave counting (Hrm\Contracts\WorkCalendar), from the attendance setup: holidays in the
 * employee's calendar, approved roster days off, the weekend profile. Same rules as attendance
 * processing (AttendanceInputs::dayTypes), so leave and attendance never disagree about a day.
 */
class AttendanceWorkCalendar implements WorkCalendar
{
    public function __construct(private AttendanceInputs $inputs) {}

    public function daysOff(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $off = [];
        foreach ($this->inputs->dayTypes($employee, $from, $to) as $date => $type) {
            if ($type !== DayType::Workday) {
                $off[$date] = true;
            }
        }

        return $off;
    }
}
