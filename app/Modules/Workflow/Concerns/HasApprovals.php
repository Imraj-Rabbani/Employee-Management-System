<?php

namespace App\Modules\Workflow\Concerns;

use App\Models\User;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Models\Approval;
use App\Modules\Workflow\Services\WorkflowEngine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Shared code for every Approvable model. A trait is copy-paste done by PHP:
 * these methods become part of each class that says "use HasApprovals;".
 *
 *     $leave->submitForApproval()    start the workflow (throws WorkflowException with a user message)
 *     $leave->approvals              every approval row of this document, level order
 *     $leave->approvalStatus()       null (never submitted), Pending, Approved, Rejected or Review
 */
trait HasApprovals
{
    /**
     * Send this document into its approval workflow (WorkflowEngine::start()).
     *
     * @return Collection<int, Approval> the new pending rows
     */
    public function submitForApproval(?User $requester = null): Collection
    {
        return app(WorkflowEngine::class)->start($this, $requester);
    }

    /**
     * The other side of Approval::approvable(). morphMany fills approvable_type with
     * this model's class and approvable_id with its id.
     *
     * @return MorphMany<Approval, $this>
     */
    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable')->orderBy('level')->orderBy('id');
    }

    /**
     * Status of the document as a whole:
     *   - no approval rows            → null (not submitted yet)
     *   - any row still pending       → Pending (someone still has to answer)
     *   - otherwise                   → the most recent answer (approved / rejected / review)
     *
     * In a list, eager load first (Model::with('approvals')) so this does not run one query per row.
     */
    public function approvalStatus(): ?ApprovalStatus
    {
        $rows = $this->relationLoaded('approvals') ? $this->approvals : $this->approvals()->get();

        if ($rows->isEmpty()) {
            return null;
        }

        if ($rows->contains(fn (Approval $a) => $a->status === ApprovalStatus::Pending)) {
            return ApprovalStatus::Pending;
        }

        /** @var Approval $latest */
        $latest = $rows->sortBy([['responded_at', 'desc'], ['id', 'desc']])->first();

        return $latest->status;
    }
}
