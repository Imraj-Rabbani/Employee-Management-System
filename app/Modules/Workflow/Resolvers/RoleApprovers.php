<?php

namespace App\Modules\Workflow\Resolvers;

use App\Models\User;
use App\Modules\Core\Models\UserRole;
use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Contracts\ResolvesApprovers;
use App\Modules\Workflow\Models\WorkflowStep;

/**
 * ApproverType::Role: every login of this company that has the step's role.
 * Each one gets their own approval row; the first answer counts (task 3.4).
 */
class RoleApprovers implements ResolvesApprovers
{
    public function approvers(WorkflowStep $step, Approvable $document, ?int $requesterUserId): array
    {
        if ($step->approver_role_id === null) {
            return [];
        }

        $userIds = UserRole::where('role_id', $step->approver_role_id)->pluck('user_id');

        // user_roles is in the tenant DB, users in the central DB: keep only logins that
        // still exist and belong to this company.
        return User::query()
            ->whereIn('id', $userIds)
            ->where('tenant_id', tenant()?->id)
            ->when($requesterUserId, fn ($q) => $q->whereKeyNot($requesterUserId))
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
