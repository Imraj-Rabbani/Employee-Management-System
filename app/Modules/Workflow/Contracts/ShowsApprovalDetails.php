<?php

namespace App\Modules\Workflow\Contracts;

/**
 * OPTIONAL extra for an Approvable: shows the document's details to the approver (5.8), in the
 * approval modal and on the approve-from-email page. Without it, only approvalSubject() is shown.
 *
 *     public function approvalDetailsView(): string
 *     {
 *         return 'hrm::approvals.profile-update';   // gets $document
 *     }
 */
interface ShowsApprovalDetails
{
    /** A Blade view name. It receives the document as $document. */
    public function approvalDetailsView(): string;
}
