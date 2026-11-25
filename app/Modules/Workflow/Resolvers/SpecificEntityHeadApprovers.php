<?php

namespace App\Modules\Workflow\Resolvers;

use App\Modules\Workflow\Models\WorkflowStep;

/**
 * ApproverType::SpecificEntityHead: the head of the ONE entity chosen on the level
 * (workflow_steps.approver_entity_id), e.g. the head of Branch "Gulshan", whoever asks.
 *
 * Same rules as EntityHeadApprovers once the entity is known: no head with a login, or the head
 * is the document's employee themself → the parent entity's head, and so on up.
 */
class SpecificEntityHeadApprovers extends EntityHeadApprovers
{
    protected function startingEntity(WorkflowStep $step, array $entityIds, ?int $employeeId): ?int
    {
        return $step->approver_entity_id;
    }
}
