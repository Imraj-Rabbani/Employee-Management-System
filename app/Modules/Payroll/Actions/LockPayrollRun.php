<?php

namespace App\Modules\Payroll\Actions;

use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Models\AttendanceDay;
use App\Modules\Payroll\Models\PayrollRun;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Locks an approved run (PR-06): from now on nothing about it changes (PayrollRun::booted refuses edits, the rows
 * refuse writes, the policy offers nothing but viewing). Also closes the period's attendance for its employees
 * (attendance_days.is_locked: never processed again), since what was paid must keep matching what was counted.
 *
 *     approved → locked → (Phase 13: disbursed)
 *
 * After locking, a change for that month is a back-dated change: the next run pays the difference as arrears (10.7);
 * adjustments for the month are refused (RequestAdjustment).
 */
class LockPayrollRun
{
    public function problem(PayrollRun $run): ?string
    {
        return $run->status === PayrollRunStatus::Approved ? null : __('Only an approved run can be locked; this one is :status.', ['status' => strtolower($run->status->label())]);
    }

    public function handle(PayrollRun $run): PayrollRun
    {
        if ($problem = $this->problem($run)) {
            throw new InvalidArgumentException($problem);
        }

        return $run->getConnection()->transaction(function () use ($run) {
            // ONE save: once the status is Locked the run refuses any other change.
            $run->forceFill(['status' => PayrollRunStatus::Locked, 'locked_at' => now(), 'locked_by_user_id' => Auth::id()])->save();

            AttendanceDay::query()
                ->whereIn('employee_id', $run->employees()->select('employee_id'))
                ->whereBetween('date', [$run->period_start->toDateString(), $run->period_end->toDateString()])
                ->update(['is_locked' => true]);

            return $run;
        });
    }
}
