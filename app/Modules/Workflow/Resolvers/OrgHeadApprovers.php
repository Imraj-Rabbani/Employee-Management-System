<?php

namespace App\Modules\Workflow\Resolvers;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Contracts\ResolvesApprovers;
use App\Modules\Workflow\Models\WorkflowStep;

/**
 * ApproverType::OrgHead: the head of the document's org unit (approvalContext()['org_unit_id'],
 * else the employee's unit).
 *
 * Walks UP the tree when the unit has no head with a login, or when the head is the employee or the
 * requester themself (a head's own request goes to the head of the unit above).
 */
class OrgHeadApprovers implements ResolvesApprovers
{
    public function approvers(WorkflowStep $step, Approvable $document, ?int $requesterUserId): array
    {
        $context = $document->approvalContext();
        $employees = Employee::withoutGlobalScope(VisibleToCurrentUser::class);

        $unitId = $context['org_unit_id']
            ?? (isset($context['employee_id']) ? (clone $employees)->whereKey($context['employee_id'])->value('org_unit_id') : null);
        $seen = [];

        while ($unitId !== null && ! in_array($unitId, $seen, true)) {
            $seen[] = $unitId;
            $unit = OrgUnit::query()->find($unitId, ['id', 'parent_id', 'head_employee_id']);
            if ($unit === null) {
                break;
            }

            if ($unit->head_employee_id !== null && $unit->head_employee_id !== ($context['employee_id'] ?? null)) {
                $userId = (clone $employees)->whereKey($unit->head_employee_id)->value('user_id');
                if ($userId !== null && (int) $userId !== $requesterUserId) {
                    return [(int) $userId];
                }
            }

            $unitId = $unit->parent_id;
        }

        return [];
    }
}
