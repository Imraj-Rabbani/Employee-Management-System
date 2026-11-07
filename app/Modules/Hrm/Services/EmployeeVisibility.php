<?php

namespace App\Modules\Hrm\Services;

use App\Models\User;
use App\Modules\Core\Services\Permissions;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\OrgUnit;

/**
 * Which employees may this login see? Applies the role's org-unit scope (Role::$org_unit_id). [FND-05]
 *
 *     app(EmployeeVisibility::class)->orgUnitIdsFor($user)   null = everyone, [3, 7, 8] = only these units
 *     app(EmployeeVisibility::class)->canSee($user, $emp)    one employee
 *
 * Rules:
 *   - Administrator, or a role without an org unit → every employee
 *   - a role limited to "Finance" → employees of Finance AND every unit below it (Accounts, Payables ...)
 *   - no role in this company → nobody
 *   - everyone always sees their OWN employee record (ESS)
 *
 * Used by the VisibleToCurrentUser global scope (every Employee query) and by EmployeePolicy.
 * Registered "scoped": the unit list is worked out once per request, not once per query.
 */
class EmployeeVisibility
{
    /** @var array<string, list<int>> "tenantId:unitId" => that unit + all units below it */
    private array $unitIds = [];

    public function __construct(private Permissions $permissions) {}

    /** @return list<int>|null null = no limit */
    public function orgUnitIdsFor(User $user): ?array
    {
        $limit = $this->permissions->orgUnitLimit($user);

        if ($limit === null) {
            return null;
        }
        if ($limit === false) {
            return [];
        }

        return $this->unitIds[tenant()?->id.':'.$limit] ??= [
            $limit,
            ...(OrgUnit::find($limit)?->descendantIds() ?? []),
        ];
    }

    public function canSee(User $user, Employee $employee): bool
    {
        if ($employee->user_id !== null && $employee->user_id === $user->id) {
            return true;
        }

        $ids = $this->orgUnitIdsFor($user);

        return $ids === null || in_array($employee->org_unit_id, $ids, true);
    }
}
