<?php

namespace App\Modules\Core\Services;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Answers "may this login do X in the current company?" for company permission keys
 * (e.g. "payroll.run.approve"). Called by the Gate::before hook in CoreServiceProvider,
 * so everywhere you simply write:
 *
 *     Blade:             can('hrm.employee.view') ... endcan   (with @ in front of both)
 *     PHP / Livewire:    Gate::authorize('payroll.run.approve');
 *     Routes:            ->middleware('can:core.roles.manage')
 *
 * Rules:
 *   - the key's module must be switched on for the company (even for admins)
 *   - the Administrator role has every key
 *   - otherwise the user's role must have the key
 *   - no role, no company, or another company → no
 *
 * Remembers each user's role and keys for the rest of the request ("scoped"), so many checks cost one lookup.
 */
class Permissions
{
    /** @var array<int, array{role: bool, admin: bool, keys: array<string, true>, org_unit_id: int|null}> user id => what they may do */
    private array $resolved = [];

    public function check(User $user, string $key): bool
    {
        $module = Str::before($key, '.');

        if (! tenant()?->hasModule($module)) {
            return false;
        }

        $access = $this->resolved[$user->id] ??= $this->resolve($user);

        return $access['admin'] || isset($access['keys'][$key]);
    }

    /**
     * The org unit this login's DATA is limited to (the role's org-unit scope, legacy profiles.entity). [FND-05]
     * Used by the VisibleToCurrentUser scope on employees (Phase 5).
     *
     *   null   no limit: Administrator, or a role without an org unit
     *   int    only this org unit and the units below it
     *   false  no role in this company: no one else's data at all
     */
    public function orgUnitLimit(User $user): int|false|null
    {
        $access = $this->resolved[$user->id] ??= $this->resolve($user);

        if (! $access['role']) {
            return false;
        }

        return $access['admin'] ? null : $access['org_unit_id'];
    }

    /** Forget what was looked up, e.g. after a role's permissions were changed. */
    public function flush(): void
    {
        $this->resolved = [];
    }

    /** @return array{role: bool, admin: bool, keys: array<string, true>, org_unit_id: int|null} */
    private function resolve(User $user): array
    {
        $role = $user->role();

        if ($role === null) {
            return ['role' => false, 'admin' => false, 'keys' => [], 'org_unit_id' => null];
        }

        return [
            'role' => true,
            'admin' => $role->is_admin,
            'keys' => array_fill_keys($role->is_admin ? [] : $role->permissionKeys(), true),
            'org_unit_id' => $role->org_unit_id,
        ];
    }
}
