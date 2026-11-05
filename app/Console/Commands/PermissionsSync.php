<?php

namespace App\Console\Commands;

use App\Console\Concerns\SelectsTenants;
use App\Core\Permissions\PermissionRegistry;
use App\Core\Tenancy\TenantDatabase;
use App\Core\Tenancy\TenantManager;
use App\Modules\Core\Models\RolePermission;
use Illuminate\Console\Command;

/**
 * Brings every company's role_permissions in line with the permission keys in the code
 * (the modules' permissions.php files). Run it after removing or renaming a permission key.
 *
 *     php artisan permissions:sync              all companies
 *     php artisan permissions:sync --tenant=sss
 *     php artisan permissions:sync --dry-run    only show what would be removed
 *
 * Keys that no longer exist in the code are removed from all roles. (New keys need nothing:
 * the code is the list, so they appear on the Roles screen straight away.)
 */
class PermissionsSync extends Command
{
    use SelectsTenants;

    protected $signature = 'permissions:sync
                            {--tenant=* : Only these tenant codes (default: all tenants)}
                            {--dry-run : Show what would be removed, change nothing}';

    protected $description = 'Remove permission keys that no longer exist in the code from every company\'s roles';

    public function handle(PermissionRegistry $registry, TenantManager $tenants, TenantDatabase $databases): int
    {
        $this->components->info(count($registry->keys()).' permission keys defined in the modules.');

        $list = $this->selectedTenants();
        if ($list->isEmpty()) {
            return self::FAILURE;
        }

        foreach ($list as $tenant) {
            if (! $databases->exists($tenant)) {
                $this->components->twoColumnDetail("[{$tenant->code}] {$tenant->name}", '<fg=yellow>no database, skipped</>');

                continue;
            }

            $tenants->switchTo($tenant);

            $stale = RolePermission::whereNotIn('permission', $registry->keys());
            $keys = (clone $stale)->distinct()->orderBy('permission')->pluck('permission')->all();

            if ($keys === []) {
                $this->components->twoColumnDetail("[{$tenant->code}] {$tenant->name}", '<fg=green>up to date</>');

                continue;
            }

            $count = $this->option('dry-run') ? (clone $stale)->count() : (clone $stale)->delete();
            $verb = $this->option('dry-run') ? 'would remove' : 'removed';

            $this->components->twoColumnDetail("[{$tenant->code}] {$tenant->name}", "<fg=yellow>{$verb} {$count}</>: ".implode(', ', $keys));
        }

        $tenants->forget();

        return self::SUCCESS;
    }
}
