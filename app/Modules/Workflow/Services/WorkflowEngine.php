<?php

namespace App\Modules\Workflow\Services;

use App\Models\User;
use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Contracts\AsksApproverForAmount;
use App\Modules\Workflow\Contracts\ResolvesApprovers;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Enums\ApproverType;
use App\Modules\Workflow\Events\ApprovalAnswered;
use App\Modules\Workflow\Events\ApprovalCompleted;
use App\Modules\Workflow\Events\ApprovalRequested;
use App\Modules\Workflow\Exceptions\WorkflowException;
use App\Modules\Workflow\Models\Approval;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowStep;
use App\Modules\Workflow\Resolvers\EmployeeApprovers;
use App\Modules\Workflow\Resolvers\OrgHeadApprovers;
use App\Modules\Workflow\Resolvers\RoleApprovers;
use App\Modules\Workflow\Resolvers\SupervisorApprovers;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Moves documents through their approval workflow. [WF-03]
 *
 *     $leave->submitForApproval();                       (HasApprovals calls start())
 *     app(WorkflowEngine::class)->start($leave, $user);  same, with an explicit requester
 *     app(WorkflowEngine::class)->findWorkflow($leave);  which workflow would be used (or null)
 *     app(WorkflowEngine::class)->respond($approval, ApprovalStatus::Approved, $user, 'OK');   [WF-04]
 *
 * After each commit it fires ApprovalRequested / ApprovalAnswered; their listeners send notifications.
 */
class WorkflowEngine
{
    /**
     * Submit a document: pick its workflow, find the approvers of the first level
     * (or of every level when the type is not chained) and create their approval rows.
     *
     * @return Collection<int, Approval> the new pending rows
     *
     * @throws WorkflowException when it cannot be submitted (message is meant for the user)
     */
    public function start(Approvable&Model $document, ?User $requester = null): Collection
    {
        if (! $document->exists) {
            throw WorkflowException::notSaved();
        }

        if ($document->approvals()->where('status', ApprovalStatus::Pending)->exists()) {
            throw WorkflowException::alreadyPending($document);
        }

        $workflow = $this->findWorkflow($document) ?? throw WorkflowException::noWorkflow($document);

        $steps = $workflow->steps;
        if ($steps->isEmpty()) {
            throw WorkflowException::noSteps($workflow->name);
        }

        // Chained: only level 1 now, the next level once it approves (3.4). Not chained: all levels at once.
        $firstSteps = $workflow->approvalType->is_chained
            ? $steps->where('level', $steps->min('level'))
            : $steps;

        $requesterId = $requester->id ?? auth()->id();
        $requesterId = $requesterId === null ? null : (int) $requesterId;

        // Resolve EVERY approver before writing anything: if one level has nobody, nothing is saved.
        $rows = $this->rowsFor($firstSteps, $document, $requesterId);

        // A transaction: all rows are saved, or (on any error) none.
        /** @var Collection<int, Approval> $created */
        $created = $document->getConnection()->transaction(
            fn () => new Collection(array_map(fn (array $row) => $document->approvals()->create($row), $rows))
        );

        // Committed: now it is safe to tell the approvers (task 3.5).
        $created->each(fn (Approval $approval) => ApprovalRequested::dispatch($approval));

        return $created;
    }

    /**
     * One approver answers one approval row. [WF-04, WF-05]
     *
     *   Approved  → other approvers of the same level are no longer asked; then either the next
     *               level is asked (chained), or we wait for other levels (not chained), or it was
     *               the last answer: ApprovalCompleted fires → the document's onApproved() runs.
     *   Rejected  → every other open row is removed; ApprovalCompleted fires → onRejected() runs.
     *   Review    → "send back": every other open row is removed. The requester fixes the document
     *               and calls submitForApproval() again (a new round starts at level 1).
     *
     * Everything happens in ONE database transaction, including the document's onApproved():
     * if that fails (e.g. a salary cannot be updated), the answer is undone as well.
     *
     * @throws WorkflowException (message is meant for the user)
     */
    public function respond(
        Approval $approval,
        ApprovalStatus $decision,
        User $responder,
        ?string $comment = null,
        ?string $attachmentPath = null,
        ?float $amount = null,
    ): void {
        if ($decision === ApprovalStatus::Pending) {
            throw WorkflowException::invalidDecision();
        }

        $comment = trim((string) $comment) ?: null;

        /** @var list<Approval> $nextRows rows created for the next level */
        $nextRows = [];

        $approval->getConnection()->transaction(function () use ($approval, $decision, $responder, $comment, $attachmentPath, $amount, &$nextRows) {
            // Lock the row: if two people click at the same moment, the second waits here,
            // then sees it is no longer pending.
            $row = Approval::whereKey($approval->id)->lockForUpdate()->with('step.workflow.approvalType')->first();

            if ($row === null || $row->status !== ApprovalStatus::Pending) {
                throw WorkflowException::alreadyAnswered();
            }
            if ($row->approver_user_id !== $responder->id) {
                throw WorkflowException::notYourApproval();
            }

            $step = $row->step;
            $workflow = $step->workflow;

            if ($decision === ApprovalStatus::Rejected && ! $workflow->allow_reject) {
                throw WorkflowException::notAllowed($decision);
            }
            if ($decision === ApprovalStatus::Review && ! $workflow->allow_review) {
                throw WorkflowException::notAllowed($decision);
            }
            if ($decision !== ApprovalStatus::Approved && $comment === null) {
                throw WorkflowException::commentRequired($decision);
            }
            if ($decision === ApprovalStatus::Approved && $step->attachment_required && $attachmentPath === null) {
                throw WorkflowException::attachmentRequired();
            }

            // Documents that ask the approver for a number (7.7): required to approve, between 0 and the max.
            $document = $row->approvable;
            $asksAmount = $document instanceof AsksApproverForAmount;
            if ($decision === ApprovalStatus::Approved && $asksAmount
                && ($amount === null || $amount < 0 || $amount > $document->approverAmountMax())) {
                throw WorkflowException::amountRequired($document->approverAmountLabel(), $document->approverAmountMax());
            }

            $row->update([
                'amount' => $decision === ApprovalStatus::Approved && $asksAmount ? round((float) $amount, 2) : null,
                'status' => $decision,
                'responded_by' => $responder->id,
                'responded_at' => now(),
                'comment' => $comment,
                'attachment_path' => $attachmentPath,
            ]);

            if (! $document instanceof Approvable) {
                throw new \LogicException('Approval '.$row->id.' points to a model that is not Approvable.');
            }

            // Rows nobody needs to answer any more: same level (approve) or all levels (reject / send back).
            $document->approvals()->pending()
                ->when($decision === ApprovalStatus::Approved, fn ($q) => $q->where('level', $row->level))
                ->get()
                ->each(fn (Approval $open) => $open->delete());

            if ($decision === ApprovalStatus::Review) {
                return;
            }

            if ($decision === ApprovalStatus::Approved) {
                // Not chained: other levels may still be open.
                if ($document->approvals()->pending()->exists()) {
                    return;
                }

                // Chained: ask the next level, if there is one.
                $next = $workflow->approvalType->is_chained
                    ? $workflow->steps->where('level', '>', $row->level)->sortBy('level')->first()
                    : null;

                if ($next !== null) {
                    foreach ($this->rowsFor([$next], $document, $row->requester_user_id) as $newRow) {
                        $nextRows[] = $document->approvals()->create($newRow);
                    }

                    return;
                }
            }

            // Final answer. The listener runs NOW, inside this transaction.
            ApprovalCompleted::dispatch($document, $row->fresh() ?? $row);
        });

        $approval->refresh();

        // Committed: notify (task 3.5). If anything above threw, we never get here and nobody is told.
        ApprovalAnswered::dispatch($approval);
        foreach ($nextRows as $newRow) {
            ApprovalRequested::dispatch($newRow);
        }
    }

    /**
     * Pending approval rows for these steps: one per approver.
     *
     * @param  iterable<WorkflowStep>  $steps
     * @return list<array<string, mixed>>
     *
     * @throws WorkflowException when a step has nobody to approve it
     */
    private function rowsFor(iterable $steps, Approvable $document, ?int $requesterId): array
    {
        $rows = [];
        foreach ($steps as $step) {
            $approverIds = $this->resolverFor($step->approver_type)->approvers($step, $document, $requesterId);
            if ($approverIds === []) {
                throw WorkflowException::noApprovers($step);
            }
            foreach ($approverIds as $userId) {
                $rows[] = [
                    'workflow_step_id' => $step->id,
                    'level' => $step->level,
                    'requester_employee_id' => $document->approvalContext()['employee_id'] ?? null,
                    'requester_user_id' => $requesterId,
                    'approver_user_id' => $userId,
                    'status' => ApprovalStatus::Pending,
                ];
            }
        }

        return $rows;
    }

    /**
     * The workflow for this document: active, of the document's approval type, whose scope
     * matches the document's approvalContext(). Several match → the MOST SPECIFIC one wins
     * (most scope columns filled; then the highest min_amount; then the oldest).
     */
    public function findWorkflow(Approvable $document): ?Workflow
    {
        $type = ApprovalType::forKey($document->approvalType());
        if ($type === null || ! $type->is_active) {
            return null;
        }

        $context = $document->approvalContext();

        return $type->workflows()
            ->where('is_active', true)
            ->with(['steps', 'approvalType'])
            ->orderBy('id')
            ->get()
            ->filter(fn (Workflow $w) => $this->matches($w, $context))
            ->sortBy([
                fn (Workflow $a, Workflow $b) => $this->specificity($b) <=> $this->specificity($a),
                fn (Workflow $a, Workflow $b) => (float) $b->min_amount <=> (float) $a->min_amount,
                fn (Workflow $a, Workflow $b) => $a->id <=> $b->id,
            ])
            ->first();
    }

    /**
     * An empty scope column means "any". A filled one must equal the document's value.
     *
     * @param  array<string, mixed>  $context
     */
    private function matches(Workflow $workflow, array $context): bool
    {
        if (! $this->sameId($workflow->scope_org_unit_id, $context['org_unit_id'] ?? null)) {
            return false;
        }

        if (! $this->sameId($workflow->scope_employment_type_id, $context['employment_type_id'] ?? null)) {
            return false;
        }

        if ($workflow->min_amount !== null) {
            $amount = $context['amount'] ?? null;
            if ($amount === null || (float) $amount < (float) $workflow->min_amount) {
                return false;
            }
        }

        return true;
    }

    /** Scope column empty = any value matches. Compared as ints, so "2" (from a form) equals 2. */
    private function sameId(?int $scope, mixed $value): bool
    {
        return $scope === null || ($value !== null && $scope === (int) $value);
    }

    /** How many scope columns are filled: 0 = catch-all workflow, 3 = very specific. */
    private function specificity(Workflow $workflow): int
    {
        return (int) ($workflow->scope_org_unit_id !== null)
            + (int) ($workflow->scope_employment_type_id !== null)
            + (int) ($workflow->min_amount !== null);
    }

    /** The class that finds the people for one approver type. */
    public function resolverFor(ApproverType $type): ResolvesApprovers
    {
        return match ($type) {
            ApproverType::Role => app(RoleApprovers::class),
            ApproverType::Employee => app(EmployeeApprovers::class),       // 5.8
            ApproverType::Supervisor => app(SupervisorApprovers::class),
            ApproverType::OrgHead => app(OrgHeadApprovers::class),
        };
    }
}
