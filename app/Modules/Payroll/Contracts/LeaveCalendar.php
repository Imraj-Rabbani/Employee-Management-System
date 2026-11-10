<?php

namespace App\Modules\Payroll\Contracts;

use App\Modules\Hrm\Models\Employee;
use Carbon\CarbonImmutable;

/**
 * Which days an employee is on APPROVED leave. Attendance processing asks this; the Leave module (Phase 8)
 * answers it. Until then NoLeaveCalendar says "never on leave".
 */
interface LeaveCalendar
{
    /** @return array<string, true> 'Y-m-d' => true for each leave day between $from and $to (inclusive) */
    public function leaveDates(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array;
}
