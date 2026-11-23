<?php

namespace App\Modules\Payroll\Policies;

use App\Models\User;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Models\PayrollRun;

/**
 * What may be done with a run, by permission AND status (PR-06). Linked by #[UsePolicy] on PayrollRun:
 *
 *     Gate::authorize('submit', $run);        @can('lock', $run) ... @endcan
 *
 *   update / delete / calculate   payroll.run.process, while Draft or Processed (delete: also not Processing)
 *   submit                        payroll.run.process, Processed
 *   withdraw (the submission)     payroll.run.process, Pending
 *   lock                          payroll.run.approve, Approved   (after that nothing changes any more)
 *
 * Approving itself is the workflow's job (the approvers of the "Payroll run" workflow), not a permission here.
 * Gate::before returns null for these ability names (they are not permission keys), so this policy decides.
 */
class PayrollRunPolicy
{
    public function update(User $user, PayrollRun $run): bool
    {
        return $user->can('payroll.run.process') && $run->status->isEditable();
    }

    public function delete(User $user, PayrollRun $run): bool
    {
        return $user->can('payroll.run.process') && $run->status->isEditable();
    }

    public function calculate(User $user, PayrollRun $run): bool
    {
        return $user->can('payroll.run.process') && ($run->status->isEditable() || $run->status === PayrollRunStatus::Processing);
    }

    public function submit(User $user, PayrollRun $run): bool
    {
        return $user->can('payroll.run.process') && $run->status === PayrollRunStatus::Processed;
    }

    public function withdraw(User $user, PayrollRun $run): bool
    {
        return $user->can('payroll.run.process') && $run->status === PayrollRunStatus::Pending;
    }

    public function lock(User $user, PayrollRun $run): bool
    {
        return $user->can('payroll.run.approve') && $run->status === PayrollRunStatus::Approved;
    }
}
