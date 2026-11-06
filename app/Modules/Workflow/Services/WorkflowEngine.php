<?php

namespace App\Modules\Workflow\Services;

use App\Models\User;
use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Contracts\ResolvesApprovers;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Enums\ApproverType;
use App\Modules\Workflow\Exceptions\WorkflowException;
use App\Modules\Workflow\Models\Approval;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Resolvers\RoleApprovers;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Moves documents through their approval workflow. [WF-03]
 *
 *     $leave->submitForApproval();                       (HasApprovals calls start())
 *     app(WorkflowEngine::class)->start($leave, $user);  same, with an explicit requester
 *     app(WorkflowEngine::class)->findWorkflow($leave);  which workflow would be used (or null)
 *
 * respond() (approve / reject / send back) comes in task 3.4, notifications in 3.5.
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
        $rows = [];
        foreach ($firstSteps as $step) {
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

        // All rows or none (task 3.4 explains transactions in more detail).
        /** @var Collection<int, Approval> $created */
        $created = $document->getConnection()->transaction(
            fn () => new Collection(array_map(fn (array $row) => $document->approvals()->create($row), $rows))
        );

        return $created;
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
            // Phase 5 (employees): EmployeeApprovers, SupervisorApprovers, OrgHeadApprovers.
            ApproverType::Employee, ApproverType::Supervisor, ApproverType::OrgHead => throw WorkflowException::approverTypeNotReady($type),
        };
    }
}
