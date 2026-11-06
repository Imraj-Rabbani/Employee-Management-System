<?php

namespace App\Modules\Workflow\Exceptions;

use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Enums\ApproverType;
use App\Modules\Workflow\Models\WorkflowStep;
use RuntimeException;

/**
 * Something stops a document from going into (or through) a workflow.
 * The message is written for the user: show it as an error toast.
 *
 *     throw WorkflowException::noWorkflow($leave);
 */
class WorkflowException extends RuntimeException
{
    public static function notSaved(): self
    {
        return new self(__('Save the document before submitting it for approval.'));
    }

    public static function alreadyPending(Approvable $document): self
    {
        return new self(__(':subject is already waiting for approval.', ['subject' => $document->approvalSubject()]));
    }

    public static function noWorkflow(Approvable $document): self
    {
        return new self(__('No active approval workflow matches :subject. Ask an administrator to set one up.', [
            'subject' => $document->approvalSubject(),
        ]));
    }

    public static function noSteps(string $workflowName): self
    {
        return new self(__('The workflow ":name" has no levels.', ['name' => $workflowName]));
    }

    public static function noApprovers(WorkflowStep $step): self
    {
        return new self(__('Nobody can approve level :level (:type).', [
            'level' => $step->level,
            'type' => $step->approver_type->label(),
        ]));
    }

    public static function approverTypeNotReady(ApproverType $type): self
    {
        return new self(__('":type" approvers need employee records, which are not available yet.', ['type' => $type->label()]));
    }
}
