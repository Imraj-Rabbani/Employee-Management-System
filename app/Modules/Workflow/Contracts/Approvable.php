<?php

namespace App\Modules\Workflow\Contracts;

use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Models\Approval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A model that can go through an approval workflow: leave applications, loans, increments ...
 * An interface is a promise: "this class HAS these methods". The workflow engine only talks
 * to documents through these methods, so it never needs to know what a leave or a loan is.
 *
 *     class LeaveApplication extends TenantModel implements Approvable
 *     {
 *         use HasApprovals;   // gives approvals() and approvalStatus()
 *         ... the five methods below ...
 *     }
 */
interface Approvable
{
    /** approval_types.key, e.g. 'leave'. Picks which workflows apply. */
    public function approvalType(): string;

    /** One line for the inbox, e.g. "Annual leave, 3 days (12–14 Nov)". */
    public function approvalSubject(): string;

    /**
     * Facts the engine uses to find the approvers:
     * ['employee_id' => 5, 'entity_ids' => [2, 9], 'employment_type_id' => 1, 'amount' => 50000]
     * employee_id: whose supervisor / entity heads are asked ("Employee's Reporting Supervisor" ...).
     * entity_ids: the document's entities, e.g. the employee's current Department and Branch
     * ($employee->entityIds()), or the one entity a roster / manpower request is for.
     * employment_type_id / amount: information only since each type has one workflow (2026-11-24).
     * Leave out what does not apply.
     *
     * @return array{employee_id?: int|null, entity_ids?: list<int>, employment_type_id?: int|null, amount?: float|int|string|null}
     */
    public function approvalContext(): array;

    /** Called once the LAST level approves. Do the real work here (debit leave, apply increment ...). */
    public function onApproved(Approval $final): void;

    /** Called when someone rejects. */
    public function onRejected(Approval $final): void;

    /**
     * Provided by the HasApprovals trait.
     *
     * @return MorphMany<Approval, covariant Model>
     */
    public function approvals(): MorphMany;

    /** Provided by the HasApprovals trait: null (never submitted), Pending, Approved, Rejected or Review. */
    public function approvalStatus(): ?ApprovalStatus;
}
