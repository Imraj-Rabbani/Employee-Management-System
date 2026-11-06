<?php

namespace App\Modules\Workflow\Events;

use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Models\Approval;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A document's workflow is finished: approved at the last level, or rejected. [WF-05]
 * An event is a message "this happened"; listeners decide what to do about it.
 * The engine does not need to know who listens.
 *
 *     ApprovalCompleted::dispatch($document, $finalApproval);
 *
 * Listeners: RunDocumentHandler (calls onApproved / onRejected). Task 3.5 adds notifications.
 */
class ApprovalCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly Approvable $document,
        public readonly Approval $final,   // the answer that finished it
    ) {}

    public function approved(): bool
    {
        return $this->final->status === ApprovalStatus::Approved;
    }
}
