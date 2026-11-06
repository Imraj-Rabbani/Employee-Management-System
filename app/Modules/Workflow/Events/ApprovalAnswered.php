<?php

namespace App\Modules\Workflow\Events;

use App\Modules\Workflow\Models\Approval;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An approver answered one row (approved, rejected or sent back). Fired AFTER the database commit.
 * Unlike ApprovalCompleted it also fires for in-between levels.
 *
 * Listener: NotifyRequester (tells the requester once the document is finished or sent back).
 */
class ApprovalAnswered
{
    use Dispatchable;

    public function __construct(public readonly Approval $approval) {}
}
