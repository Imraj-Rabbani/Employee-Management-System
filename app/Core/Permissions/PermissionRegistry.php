<?php

namespace App\Core\Permissions;

use InvalidArgumentException;

/**
 * Every permission key the code knows about, collected from each module's permissions.php.
 *
 *     app/Modules/Hrm/permissions.php
 *     return [
 *         'Employees' => [                                  group heading on the Roles screen
 *             'hrm.employee.view' => 'View employees',      key => label
 *         ],
 *     ];
 *
 * Keys must start with the module name ("hrm."), so two modules can never clash.
 * The list lives in code (not a table), so a new permission is available as soon as it is deployed.
 * One shared instance (singleton), filled by ModuleServiceProvider.
 */
class PermissionRegistry
{
    /** @var array<string, array<string, array<string, string>>> module => group => key => label */
    private array $modules = [];

    /** @var array<string, string> key => label */
    private array $labels = [];

    /** @param  array<string, array<string, string>>  $groups */
    public function addModule(string $module, array $groups): void
    {
        foreach ($groups as $group => $permissions) {
            foreach ($permissions as $key => $label) {
                if (! str_starts_with($key, $module.'.')) {
                    throw new InvalidArgumentException("Permission [{$key}] in module [{$module}] must start with \"{$module}.\".");
                }

                $this->labels[$key] = $label;
            }
        }

        $this->modules[$module] = $groups;
    }

    public function has(string $key): bool
    {
        return isset($this->labels[$key]);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->labels);
    }

    public function label(string $key): ?string
    {
        return $this->labels[$key] ?? null;
    }

    /** @return array<string, array<string, array<string, string>>> module => group => key => label */
    public function grouped(): array
    {
        return $this->modules;
    }
}
