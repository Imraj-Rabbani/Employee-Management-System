<?php

namespace App\Modules\Workflow\Exceptions;

use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Enums\ApprovalStatus;
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

    // ---------------------------------------------------------------- respond() (task 3.4)

    public static function amountRequired(string $label, float $max): self
    {
        return new self(__(':label: enter a number from 0 to :max to approve.', ['label' => $label, 'max' => rtrim(rtrim(number_format($max, 2, '.', ''), '0'), '.')]));
    }

    public static function invalidDecision(): self
    {
        return new self(__('Choose approve, reject or send back.'));
    }

    public static function alreadyAnswered(): self
    {
        return new self(__('This approval has already been answered.'));
    }

    public static function notYourApproval(): self
    {
        return new self(__('You are not the approver of this request.'));
    }

    public static function notAllowed(ApprovalStatus $decision): self
    {
        return new self(__('":action" is not allowed in this workflow.', ['action' => $decision->label()]));
    }

    public static function commentRequired(ApprovalStatus $decision): self
    {
        return new self(__('Please give a reason (":action").', ['action' => $decision->label()]));
    }

    public static function attachmentRequired(): self
    {
        return new self(__('An attachment is required at this level.'));
    }
}
