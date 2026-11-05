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
    /** @var array<int, array{admin: bool, keys: array<string, true>}> user id => what they may do */
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

    /** Forget what was looked up, e.g. after a role's permissions were changed. */
    public function flush(): void
    {
        $this->resolved = [];
    }

    /** @return array{admin: bool, keys: array<string, true>} */
    private function resolve(User $user): array
    {
        $role = $user->role();

        if ($role === null) {
            return ['admin' => false, 'keys' => []];
        }

        return [
            'admin' => $role->is_admin,
            'keys' => array_fill_keys($role->is_admin ? [] : $role->permissionKeys(), true),
        ];
    }
}
