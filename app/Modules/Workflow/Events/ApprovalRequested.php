<?php

namespace App\Modules\Workflow\Events;

use App\Modules\Workflow\Models\Approval;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Someone has been asked to approve (a new pending row). Fired AFTER the database commit,
 * so nobody is notified about a row that was rolled back.
 *
 * Listener: NotifyApprover.
 */
class ApprovalRequested
{
    use Dispatchable;

    public function __construct(public readonly Approval $approval) {}
}
