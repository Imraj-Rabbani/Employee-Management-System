<?php

namespace App\Modules\Payroll\Stages;

use App\Modules\Payroll\Contracts\LeaveCalendar;
use App\Modules\Payroll\Data\PayrollContext;
use App\Modules\Payroll\Enums\AttendanceStatus;
use App\Modules\Payroll\Models\AttendanceDay;
use App\Modules\Payroll\Services\PayrollSettings;
use Closure;

/**
 * Stage 3. The days the employee is paid for (legacy: fractional month pay + LWP in insertPayroll):
 *
 *   days worked   workFrom..workTo (ResolvePeriod already cut the period at joining / separation)
 *   − LWP         approved leave of an LWP type (LeaveCalendar; half day = 0.5)      pay scale: deduct_lwp
 *   − absent      attendance_days Absent = 1, Half day = 0.5 (not on an LWP day)      pay scale: deduct_absent
 *   = payable     never below 0
 *
 * The rules come from the employee's pay scale profile (PayrollSettings::payScaleFor); without one both are deducted.
 *
 * Late joiners: with a threshold day (pay scale), someone who joined in this period AFTER that day of the month is
 * not paid in this run (payable 0, joinerDeferred, a warning); the next run pays those days as arrears (Stages\Arrears).
 *
 * Attendance: an employee with NO processed days in the period is taken as "not on attendance" (nothing deducted,
 * no warning). Some days processed but others missing → a warning: the missing days are not counted as absent.
 *
 * Not here: LWP from late attendance (n lates = 1 day, pay scale ignore_late_lwp) is not worked out anywhere yet.
 */
class ComputePayableDays
{
    public function __construct(
        private readonly LeaveCalendar $leave,
        private readonly PayrollSettings $settings,
    ) {}

    public function handle(PayrollContext $ctx, Closure $next): mixed
    {
        $ctx->payScale = $this->settings->payScaleFor($ctx->employee);
        $ctx->lwpDays = 0.0;
        $ctx->absentDays = 0.0;
        $worked = (float) ((int) $ctx->workFrom->diffInDays($ctx->workTo) + 1);

        if (! $ctx->ignoreThreshold && $this->joinedAfterThreshold($ctx)) {
            $ctx->payableDays = 0.0;
            $ctx->joinerDeferred = true;
            $ctx->warn(__('Joined on :date, after the threshold day :day: this period is paid with next month\'s salary as arrears.', [
                'date' => format_date($ctx->employee->join_date), 'day' => $ctx->payScale->threshold_day,
            ]));

            return $next($ctx);
        }

        $unpaid = $this->leave->unpaidLeaveDays($ctx->employee, $ctx->workFrom, $ctx->workTo);
        $lwp = (float) array_sum($unpaid);
        if ($lwp > 0 && ($ctx->payScale?->deduct_lwp ?? true)) {
            $ctx->lwpDays = $lwp;
        } elseif ($lwp > 0) {
            $ctx->warn(__(':days day(s) of leave without pay not deducted (pay scale profile :name).', [
                'days' => PayrollContext::days($lwp), 'name' => $ctx->payScale->name,
            ]));
        }

        if ($ctx->payScale?->deduct_absent ?? true) {
            $ctx->absentDays = $this->absentDays($ctx, $unpaid, $worked);
        }

        $ctx->payableDays = max(0.0, $worked - $ctx->lwpDays - $ctx->absentDays);
        if ($ctx->payableDays <= 0) {
            $ctx->warn(__('No payable days in :period: nothing is paid.', ['period' => $ctx->period->label()]));
        }

        return $next($ctx);
    }

    private function joinedAfterThreshold(PayrollContext $ctx): bool
    {
        $threshold = $ctx->payScale?->threshold_day ?? 0;
        $join = $ctx->employee->join_date;

        return $threshold > 0
            && $join->toDateString() >= $ctx->period->start()->toDateString()
            && $join->day > $threshold;
    }

    /** @param  array<string, float>  $unpaid  LWP dates: a day already taken as LWP is never absent too */
    private function absentDays(PayrollContext $ctx, array $unpaid, float $worked): float
    {
        $days = AttendanceDay::query()
            ->where('employee_id', $ctx->employee->id)
            ->whereBetween('date', [$ctx->workFrom->toDateString(), $ctx->workTo->toDateString()])
            ->get(['date', 'status']);

        if ($days->isEmpty()) {
            return 0.0;   // not on attendance
        }

        $missing = (int) $worked - $days->count();
        if ($missing > 0) {
            $ctx->warn(__(':count of :total days have no processed attendance: they are not counted as absent.', ['count' => $missing, 'total' => (int) $worked]));
        }

        $absent = 0.0;
        foreach ($days as $day) {
            if (isset($unpaid[$day->date->toDateString()])) {
                continue;
            }
            $absent += match ($day->status) {
                AttendanceStatus::Absent => 1.0,
                AttendanceStatus::HalfDay => 0.5,
                default => 0.0,
            };
        }

        return $absent;
    }
}
