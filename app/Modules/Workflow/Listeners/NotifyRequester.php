<?php

namespace App\Modules\Workflow\Listeners;

use App\Models\User;
use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Events\ApprovalAnswered;
use App\Modules\Workflow\Notifications\ApprovalDecided;

/**
 * ApprovalAnswered → tell the requester, but only when it matters to them:
 * finally approved, rejected, or sent back. (Level 1 of 3 approving is not news yet.)
 */
class NotifyRequester
{
    public function handle(ApprovalAnswered $event): void
    {
        $approval = $event->approval->loadMissing('step.workflow.approvalType');
        $requester = User::find($approval->requester_user_id);
        $document = $approval->approvable;

        if ($requester === null || ! $document instanceof Approvable) {
            return;
        }

        // Approved at a middle level: the document is still pending, so nothing to say yet.
        if ($approval->status === ApprovalStatus::Approved && $document->approvalStatus() !== ApprovalStatus::Approved) {
            return;
        }

        $requester->notify(new ApprovalDecided(
            approvalId: $approval->id,
            typeName: $approval->step->workflow->approvalType->name,
            subject: $document->approvalSubject(),
            status: $approval->status,
            responderName: User::find($approval->responded_by)?->name,
            comment: $approval->comment,
            sendEmail: $approval->step->workflow->send_email,
        ));
    }
}
