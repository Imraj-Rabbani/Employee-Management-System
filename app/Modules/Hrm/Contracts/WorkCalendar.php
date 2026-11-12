<?php

namespace App\Modules\Hrm\Contracts;

use App\Modules\Hrm\Models\Employee;
use Carbon\CarbonImmutable;

/**
 * Which days an employee does not work: weekends, holidays, roster days off. Leave counting asks this
 * (8.3: "do not count weekends and holidays", sandwich rule). The attendance setup lives in the Payroll
 * module, which binds the real answer (AttendanceWorkCalendar); without it NoWorkCalendar says "every day
 * is a working day".
 *
 * The mirror of Payroll\Contracts\LeaveCalendar (attendance asks the leave side).
 */
interface WorkCalendar
{
    /** @return array<string, true> 'Y-m-d' => true for each day off between $from and $to (inclusive) */
    public function daysOff(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array;
}
