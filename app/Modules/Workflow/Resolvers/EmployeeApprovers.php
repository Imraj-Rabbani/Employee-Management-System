<?php

namespace App\Modules\Workflow\Resolvers;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Contracts\ResolvesApprovers;
use App\Modules\Workflow\Models\WorkflowStep;

/**
 * ApproverType::Employee: the login of the one employee chosen on the step (approver_employee_id).
 * Nobody when that employee has no login (5.7) or is the requester.
 *
 * withoutGlobalScope: the REQUESTER's visibility must not decide who approves (an employee in self
 * service can only "see" themselves).
 */
class EmployeeApprovers implements ResolvesApprovers
{
    public function approvers(WorkflowStep $step, Approvable $document, ?int $requesterUserId): array
    {
        $userId = Employee::withoutGlobalScope(VisibleToCurrentUser::class)->whereKey($step->approver_employee_id)->value('user_id');

        return $userId !== null && (int) $userId !== $requesterUserId ? [(int) $userId] : [];
    }
}
