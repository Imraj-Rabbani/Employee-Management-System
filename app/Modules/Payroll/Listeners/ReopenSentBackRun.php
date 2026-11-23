<?php

namespace App\Modules\Payroll\Listeners;

use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Events\ApprovalAnswered;

/**
 * An approver SENT BACK a payroll run ("review"): the workflow only removes the open approvals, so the run is put
 * back to Processed here, where it can be fixed, calculated again and submitted again.
 */
class ReopenSentBackRun
{
    public function handle(ApprovalAnswered $event): void
    {
        $approval = $event->approval;
        if ($approval->status !== ApprovalStatus::Review || $approval->approvable_type !== (new PayrollRun)->getMorphClass()) {
            return;
        }

        $run = PayrollRun::find($approval->approvable_id);
        if ($run !== null && $run->status === PayrollRunStatus::Pending) {
            $run->moveTo(PayrollRunStatus::Processed);
        }
    }
}
