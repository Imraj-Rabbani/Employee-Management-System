<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Contracts\LeaveCalendar;
use Carbon\CarbonImmutable;

/** Stand-in until the Leave module exists: nobody is ever on leave. */
class NoLeaveCalendar implements LeaveCalendar
{
    public function leaveDates(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [];
    }

    public function unpaidLeaveDays(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [];
    }
}
