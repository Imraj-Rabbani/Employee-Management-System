<?php

namespace App\Modules\Hrm\Policies;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Services\EmployeeVisibility;

/**
 * A POLICY: the permission rules for one model, one method per action. Laravel finds it through
 * #[UsePolicy] on Employee, so you write:
 *
 *     Gate::authorize('update', $employee);           PHP / Livewire
 *     can('view', $employee) ... endcan               Blade (with @ in front)
 *     ->can('viewAny', Employee::class)               routes
 *
 * Each rule = the permission key from permissions.php AND the employee is inside the user's
 * org-unit scope (the same rule as the VisibleToCurrentUser global scope, checked again here
 * in case a record was loaded withoutGlobalScope).
 *
 * Gate::before (CoreServiceProvider) returns null for these ability names, so the policy decides.
 */
class EmployeePolicy
{
    public function __construct(private EmployeeVisibility $visibility) {}

    public function viewAny(User $user): bool
    {
        return $user->can('hrm.employee.view');
    }

    /** Your own record is always viewable (ESS); changing it goes through a profile update request (5.8). */
    public function view(User $user, Employee $employee): bool
    {
        if ($employee->user_id !== null && $employee->user_id === $user->id) {
            return true;
        }

        return $user->can('hrm.employee.view') && $this->visibility->canSee($user, $employee);
    }

    public function create(User $user): bool
    {
        return $user->can('hrm.employee.create');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can('hrm.employee.update') && $this->inScope($user, $employee);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->can('hrm.employee.delete') && $this->inScope($user, $employee);
    }

    /** In the user's org units. Unlike view(), being the employee yourself does NOT count. */
    private function inScope(User $user, Employee $employee): bool
    {
        $ids = $this->visibility->orgUnitIdsFor($user);

        return $ids === null || in_array($employee->org_unit_id, $ids, true);
    }
}
