<?php

namespace App\Modules\Workflow\Listeners;

use App\Modules\Workflow\Events\ApprovalCompleted;

/**
 * Lets the document do its own work once its workflow is finished (legacy *OnComplete callbacks).
 * Runs immediately (NOT queued), inside the engine's transaction: if onApproved() throws,
 * the approval is undone too, so a document is never "approved" without its effect.
 */
class RunDocumentHandler
{
    public function handle(ApprovalCompleted $event): void
    {
        if ($event->approved()) {
            $event->document->onApproved($event->final);
        } else {
            $event->document->onRejected($event->final);
        }
    }
}
