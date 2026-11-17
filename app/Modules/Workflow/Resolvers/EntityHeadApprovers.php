<?php

namespace App\Modules\Workflow\Resolvers;

use App\Modules\Hrm\Models\EmployeeEntity;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Contracts\ResolvesApprovers;
use App\Modules\Workflow\Models\WorkflowStep;

/**
 * ApproverType::EntityHead: the head of the document's entity OF THE STEP'S TYPE
 * (workflow_steps.approver_entity_type_id), e.g. "Head of Department".
 *
 * Which entity:
 *   1. among the document's entities (approvalContext()['entity_ids']) the one of that type
 *      (a roster for Department "Factory" → Factory; a leave of a Finance employee → Finance)
 *   2. else the document employee's current entity of that type
 *      (a roster for Branch "Gulshan" asks the Head of Department → the requester's Department)
 *
 * When that entity has no head with a login, or the head is the employee or the requester themself,
 * it goes UP to the parent entity's head (the optional parent, e.g. Gulshan → Division Dhaka), and so on.
 */
class EntityHeadApprovers implements ResolvesApprovers
{
    public function approvers(WorkflowStep $step, Approvable $document, ?int $requesterUserId): array
    {
        $context = $document->approvalContext();
        $employees = Employee::withoutGlobalScope(VisibleToCurrentUser::class);
        $employeeId = $context['employee_id'] ?? null;

        $entityId = $this->startingEntity($step->approver_entity_type_id, $context['entity_ids'] ?? [], $employeeId);
        $seen = [];

        while ($entityId !== null && ! in_array($entityId, $seen, true)) {
            $seen[] = $entityId;
            $entity = Entity::query()->find($entityId, ['id', 'parent_id', 'head_employee_id']);
            if ($entity === null) {
                break;
            }

            if ($entity->head_employee_id !== null && $entity->head_employee_id !== $employeeId) {
                $userId = (clone $employees)->whereKey($entity->head_employee_id)->value('user_id');
                if ($userId !== null && (int) $userId !== $requesterUserId) {
                    return [(int) $userId];
                }
            }

            $entityId = $entity->parent_id;
        }

        return [];
    }

    /** @param  list<int>  $entityIds */
    private function startingEntity(?int $typeId, array $entityIds, ?int $employeeId): ?int
    {
        if ($typeId === null) {
            return $entityIds[0] ?? null;   // old steps without a type: the document's first entity
        }

        $id = Entity::query()->whereIn('id', $entityIds)->where('entity_type_id', $typeId)->value('id')
            ?? ($employeeId === null ? null : EmployeeEntity::query()
                ->where('employee_id', $employeeId)->where('entity_type_id', $typeId)->whereNull('effective_to')
                ->value('entity_id'));

        return $id === null ? null : (int) $id;
    }
}
