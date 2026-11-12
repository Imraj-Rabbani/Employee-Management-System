<?php

namespace App\Modules\Hrm\Services;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveApplicationDay;
use App\Modules\Payroll\Contracts\LeaveCalendar;
use Carbon\CarbonImmutable;

/**
 * Answers attendance processing (Payroll\Contracts\LeaveCalendar, 7.4): the counted days of the employee's
 * APPROVED, not withdrawn leave applications (8.4). Half days count as leave days too. [LV-04, TA-05]
 */
class ApprovedLeaveCalendar implements LeaveCalendar
{
    public function leaveDates(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return LeaveApplicationDay::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereHas('application', fn ($q) => $q->whereNotNull('approved_at')->whereNull('withdrawn_at'))
            ->pluck('date')
            ->mapWithKeys(fn ($date) => [CarbonImmutable::parse($date)->toDateString() => true])
            ->all();
    }
}
