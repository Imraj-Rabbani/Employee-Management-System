<?php

namespace App\Modules\Workflow\Listeners;

use App\Models\User;
use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Events\ApprovalRequested;
use App\Modules\Workflow\Notifications\ApprovalNeeded;
use Illuminate\Support\Facades\URL;

/**
 * ApprovalRequested → tell the approver (in-app + email with a signed approve/reject link). [WF-03]
 * Runs in the web request (the tenant is active); the notification itself is queued.
 */
class NotifyApprover
{
    /** How long the email link works. */
    public const LINK_DAYS = 7;

    public function handle(ApprovalRequested $event): void
    {
        $approval = $event->approval->loadMissing('step.workflow.approvalType');
        $approver = User::find($approval->approver_user_id);
        $document = $approval->approvable;

        if ($approver === null || ! $document instanceof Approvable || tenant() === null) {
            return;
        }

        $approver->notify(new ApprovalNeeded(
            approvalId: $approval->id,
            typeName: $approval->step->workflow->approvalType->name,
            subject: $document->approvalSubject(),
            level: $approval->level,
            requesterName: User::find($approval->requester_user_id)?->name,
            respondUrl: self::respondUrl($approval->id),
            sendEmail: $approval->step->workflow->send_email,
        ));
    }

    /**
     * The signed link in the email. The signature (a hash of the URL made with APP_KEY) proves
     * WE created it: change one character (another approval id, a later expiry...) and it stops working.
     * The company code is in the URL because the person is not logged in.
     */
    public static function respondUrl(int $approvalId): string
    {
        return URL::temporarySignedRoute('workflow.respond', now()->addDays(self::LINK_DAYS), [
            'tenant' => tenant()?->code,
            'approval' => $approvalId,
        ]);
    }
}
