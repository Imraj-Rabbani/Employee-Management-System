<?php

namespace App\Modules\Hrm\Actions;

use App\Modules\Hrm\Data\LeaveProblem;
use App\Modules\Hrm\Data\LeaveYear;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveEncashment;
use App\Modules\Hrm\Models\LeaveType;
use App\Modules\Hrm\Services\LeaveLedger;
use App\Modules\Workflow\Exceptions\WorkflowException;
use App\Modules\Workflow\Services\WorkflowEngine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;

/**
 * Asks to encash leave (new, or changed after a send-back) and withdraws it. [LV-06]
 * The ONE way leave_encashments are written: by HR (HRM → Leave → Encashments) and the employee (Self Service).
 *
 *     if ($problem = $action->problem($employee, $type, $values)) { show it } else { $action->handle($employee, $type, $values) }
 *
 * Asking: the type is in the employee's profile and encashable; days in half days, at most the type's
 * encash_limit and the balance of the CURRENT leave year; still employed; one waiting encashment per type.
 * Withdrawing: the employee until it is approved, HR until payroll paid it; approved days go back to the balance.
 */
class RequestEncashment
{
    public function __construct(
        private LeaveLedger $ledger,
        private WorkflowEngine $engine,
    ) {}

    /** @param  array{days: string|float, reason?: string}  $values */
    public function problem(Employee $employee, LeaveType $type, array $values, ?LeaveEncashment $existing = null): ?LeaveProblem
    {
        $days = (float) $values['days'];
        $year = LeaveYear::current();
        $today = CarbonImmutable::now(settings('timezone', config('app.timezone')))->startOfDay();

        if ($existing !== null && ! $existing->isEditable()) {
            return new LeaveProblem('not_editable', 'days', __('This encashment is waiting for approval or already decided, so it cannot be changed.'));
        }
        $inProfile = $employee->leaveProfile?->items()->where('leave_type_id', $type->id)->exists() ?? false;
        if (! $type->is_active || ! $type->is_encashable || ! $inProfile) {
            return new LeaveProblem('not_encashable', 'leave_type_id', __(':type cannot be encashed by this employee.', ['type' => $type->name]));
        }
        if ($employee->separation_date !== null && $employee->separation_date->lt($today)) {
            return new LeaveProblem('separated', 'leave_type_id', __(':name is no longer employed: final settlement pays unused leave (Phase 13).', ['name' => $employee->name]));
        }
        if ($days <= 0 || fmod($days * 2, 1.0) !== 0.0) {
            return new LeaveProblem('days', 'days', __('Days: more than 0, in half days (e.g. 2.5).'));
        }
        if ($type->encash_limit !== null && $days > $type->encash_limit) {
            return new LeaveProblem('encash_limit', 'days', __('At most :n days of :type can be encashed at a time.', ['n' => $type->encash_limit, 'type' => $type->name]));
        }

        $other = LeaveEncashment::query()->active()->whereNull('approved_at')
            ->where('employee_id', $employee->id)->where('leave_type_id', $type->id)
            ->when($existing, fn ($q) => $q->whereKeyNot($existing->id))->first();
        if ($other !== null) {
            return new LeaveProblem('waiting', 'leave_type_id', __(':no for :type is still waiting for approval.', ['no' => $other->number(), 'type' => $type->name]));
        }

        $balance = $this->ledger->balance($employee, $type, $year);
        if ($days > $balance) {
            return new LeaveProblem('balance', 'days', __('Only :n days of :type are left in :year.', ['n' => max(0, $balance), 'type' => $type->name, 'year' => $year->label()]));
        }

        return null;
    }

    /**
     * Saves and sends for approval in ONE transaction: nothing is kept when nobody can approve it.
     *
     * @param  array{days: string|float, reason?: string}  $values
     *
     * @throws WorkflowException
     */
    public function handle(Employee $employee, LeaveType $type, array $values, ?LeaveEncashment $existing = null): LeaveEncashment
    {
        return (new LeaveEncashment)->getConnection()->transaction(function () use ($employee, $type, $values, $existing) {
            $encashment = $existing ?? new LeaveEncashment(['requested_by_user_id' => Auth::id()]);
            $encashment->fill([
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'leave_year' => LeaveYear::current()->year,
                'days' => round((float) $values['days'], 2),
                'reason' => trim((string) ($values['reason'] ?? '')) ?: null,
            ])->save();

            $encashment->submitForApproval();

            return $encashment;
        });
    }

    public function withdrawProblem(LeaveEncashment $encashment, bool $byHr = false): ?string
    {
        return match (true) {
            ! $encashment->isActive() => __('This encashment was already rejected or withdrawn.'),
            $encashment->paid_at !== null || $encashment->payroll_run_id !== null => __('Payroll already paid this encashment.'),
            $encashment->approved_at !== null && ! $byHr => __('This encashment is approved: ask HR to withdraw it.'),
            default => null,
        };
    }

    public function withdraw(LeaveEncashment $encashment): void
    {
        $encashment->getConnection()->transaction(function () use ($encashment) {
            $encashment->forceFill(['withdrawn_at' => now(), 'withdrawn_by_user_id' => Auth::id()])->save();
            $this->engine->withdraw($encashment);

            if ($encashment->approved_at !== null) {
                $this->ledger->reverse($encashment, __(':no withdrawn', ['no' => $encashment->number()]));
            }
        });
    }
}
