<?php

namespace App\Modules\Workflow\Resolvers;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Contracts\ResolvesApprovers;
use App\Modules\Workflow\Models\WorkflowStep;

/**
 * ApproverType::Supervisor: the login of the supervisor of the document's employee
 * (approvalContext()['employee_id']). Nobody when there is no supervisor, or they have no login.
 */
class SupervisorApprovers implements ResolvesApprovers
{
    public function approvers(WorkflowStep $step, Approvable $document, ?int $requesterUserId): array
    {
        $employeeId = $document->approvalContext()['employee_id'] ?? null;
        if ($employeeId === null) {
            return [];
        }

        $employees = Employee::withoutGlobalScope(VisibleToCurrentUser::class);
        $supervisorId = (clone $employees)->whereKey($employeeId)->value('supervisor_id');
        $userId = $supervisorId ? (clone $employees)->whereKey($supervisorId)->value('user_id') : null;

        return $userId !== null && (int) $userId !== $requesterUserId ? [(int) $userId] : [];
    }
}
