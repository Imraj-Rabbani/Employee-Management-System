<?php

namespace App\Modules\Payroll\Contracts;

use App\Modules\Hrm\Models\Employee;
use Carbon\CarbonImmutable;

/**
 * Which days an employee is on APPROVED leave. Attendance processing and the payroll run ask this; the Leave
 * module answers it (Hrm\Services\ApprovedLeaveCalendar). NoLeaveCalendar says "never on leave".
 */
interface LeaveCalendar
{
    /** @return array<string, true> 'Y-m-d' => true for each leave day between $from and $to (inclusive) */
    public function leaveDates(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array;

    /**
     * Days of approved leave WITHOUT PAY (leave types marked LWP) between $from and $to, for the payroll run.
     *
     * @return array<string, float> 'Y-m-d' => 1.0 or 0.5
     */
    public function unpaidLeaveDays(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array;
}
