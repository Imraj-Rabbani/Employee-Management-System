<?php

namespace App\Modules\Hrm\Actions;

use App\Modules\Hrm\Models\LeaveApplication;
use App\Modules\Hrm\Services\LeaveLedger;
use App\Modules\Payroll\Models\AttendanceDay;
use App\Modules\Workflow\Services\WorkflowEngine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;

/**
 * Withdraws (cancels) a leave application. [LV-04] No approval needed.
 *
 *   not decided yet (waiting / sent back)  its pending approvals are removed (WorkflowEngine::withdraw)
 *   approved                               the days go back to the balance (LeaveLedger::reverse) and attendance
 *                                          is processed again
 *
 * Who: the employee themself while the leave has not started; HR (byHr) also later. Never on days whose
 * payroll is closed. Rejected or already withdrawn applications cannot be withdrawn.
 *
 *     if ($problem = $action->problem($application, byHr: false)) { show it } else { $action->handle($application, $reason) }
 */
class WithdrawLeave
{
    public function __construct(
        private LeaveLedger $ledger,
        private WorkflowEngine $engine,
    ) {}

    public function problem(LeaveApplication $application, bool $byHr = false): ?string
    {
        if (! $application->isActive()) {
            return __('This application was already rejected or withdrawn.');
        }

        $today = CarbonImmutable::now(settings('timezone', config('app.timezone')))->startOfDay();
        if ($application->approved_at !== null && ! $byHr && $application->span()[0]->lte($today)) {
            return __('This leave has already started: ask HR to cancel it.');
        }

        $dates = $application->leaveDays()->pluck('date')->map(fn ($d) => CarbonImmutable::parse($d)->toDateString())->all();
        if ($application->approved_at !== null
            && AttendanceDay::query()->where('employee_id', $application->employee_id)->whereIn('date', $dates)->where('is_locked', true)->exists()) {
            return __('The payroll for some of these days is closed: the leave can no longer be withdrawn.');
        }

        return null;
    }

    public function handle(LeaveApplication $application, ?string $reason = null): void
    {
        $wasApproved = $application->approved_at !== null;

        $application->getConnection()->transaction(function () use ($application, $reason, $wasApproved) {
            $application->forceFill([
                'withdrawn_at' => now(),
                'withdrawn_by_user_id' => Auth::id(),
                'withdraw_reason' => trim((string) $reason) ?: null,
            ])->save();

            $this->engine->withdraw($application);

            if ($wasApproved) {
                $this->ledger->reverse($application, __(':no withdrawn', ['no' => $application->number()]));
            }
        });

        if ($wasApproved) {
            $application->reprocessAttendance();
        }
    }
}
