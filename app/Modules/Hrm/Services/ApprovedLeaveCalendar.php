<?php

namespace App\Modules\Hrm\Services;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveApplicationDay;
use App\Modules\Payroll\Contracts\LeaveCalendar;
use Carbon\CarbonImmutable;

/**
 * Answers attendance processing (Payroll\Contracts\LeaveCalendar, 7.4) and the payroll run (10.5): the counted
 * days of the employee's APPROVED, not withdrawn leave applications. Half days count as leave days too.
 */
class ApprovedLeaveCalendar implements LeaveCalendar
{
    public function leaveDates(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $dates = LeaveApplicationDay::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereHas('application', fn ($q) => $q->whereNotNull('approved_at')->whereNull('withdrawn_at'))
            ->pluck('date');

        $leave = [];
        foreach ($dates as $date) {
            $leave[CarbonImmutable::parse($date)->toDateString()] = true;
        }

        return $leave;
    }

    /** Only leave types marked "leave without pay"; a half day is 0.5 (two half days on one date add up, at most 1). */
    public function unpaidLeaveDays(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = LeaveApplicationDay::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereHas('application', fn ($q) => $q->whereNotNull('approved_at')->whereNull('withdrawn_at')
                ->whereHas('leaveType', fn ($t) => $t->where('is_lwp', true)))
            ->get(['date', 'days']);

        $unpaid = [];
        foreach ($days as $day) {
            $date = $day->date->toDateString();
            $unpaid[$date] = min(1.0, ($unpaid[$date] ?? 0.0) + $day->days);
        }

        return $unpaid;
    }
}
