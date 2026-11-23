<?php

namespace App\Modules\Payroll\Actions;

use App\Modules\Payroll\Enums\PayrollCalcStatus;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Workflow\Exceptions\WorkflowException;
use App\Modules\Workflow\Services\WorkflowEngine;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Sends a calculated run for approval (PR-06), and takes it back before it is decided.
 *
 *     if ($problem = $action->problem($run)) { show it } else { $action->handle($run) }
 *     $action->withdraw($run)
 *
 *   processed → pending (approval type "payroll_run")
 *               → approved            the last level approves (PayrollRun::onApproved)
 *               → processed           rejected (onRejected), sent back (ReopenSentBackRun), or withdrawn here
 *
 * Only a Processed run in which EVERY employee was calculated Ok (no failed, nobody pending): failures must be fixed
 * or the people taken out first. Warnings do not stop it; the approver sees them in the approval view.
 * From Pending on, the run's numbers cannot change (GuardedByRunStatus, PayrollRun::booted).
 */
class SubmitPayrollRun
{
    public function __construct(private WorkflowEngine $engine) {}

    public function problem(PayrollRun $run): ?string
    {
        $notOk = $run->employees()->where('calc_status', '!=', PayrollCalcStatus::Ok->value)->count();

        return match (true) {
            $run->status !== PayrollRunStatus::Processed => __('Only a calculated (processed) run can be submitted; this one is :status.', ['status' => strtolower($run->status->label())]),
            ! $run->employees()->exists() => __('This run has no employees.'),
            $notOk > 0 => trans_choice(':count employee is failed or not calculated: fix that and calculate again first.|:count employees are failed or not calculated: fix that and calculate again first.', $notOk),
            default => null,
        };
    }

    /** Saved and sent in ONE transaction: nothing changes when nobody can approve it. @throws WorkflowException */
    public function handle(PayrollRun $run): PayrollRun
    {
        if ($problem = $this->problem($run)) {
            throw new InvalidArgumentException($problem);
        }

        return $run->getConnection()->transaction(function () use ($run) {
            $run->moveTo(PayrollRunStatus::Pending);
            $run->forceFill(['submitted_at' => now(), 'submitted_by_user_id' => Auth::id()])->save();
            $run->submitForApproval();

            return $run;
        });
    }

    public function withdrawProblem(PayrollRun $run): ?string
    {
        return $run->status === PayrollRunStatus::Pending ? null : __('Only a run waiting for approval can be taken back.');
    }

    /** Back to Processed; the approvers are no longer asked (answers already given stay as history). */
    public function withdraw(PayrollRun $run): void
    {
        if ($problem = $this->withdrawProblem($run)) {
            throw new InvalidArgumentException($problem);
        }

        $run->getConnection()->transaction(function () use ($run) {
            $this->engine->withdraw($run);
            $run->moveTo(PayrollRunStatus::Processed);
        });
    }
}
