<?php

namespace App\Core\Tenancy;

/**
 * The folders holding TENANT migrations (tables that go into every company's
 * database). Each module adds its Database/Migrations folder here.
 *
 * Central migrations stay in database/migrations and run with `php artisan migrate`.
 * Tenant migrations run with `php artisan tenants:migrate`.
 */
class TenantMigrationPaths
{
    /** @var list<string> */
    private array $paths = [];

    public function add(string $path): void
    {
        if (! in_array($path, $this->paths, true)) {
            $this->paths[] = $path;
        }
    }

    /** @return list<string> */
    public function all(): array
    {
        return $this->paths;
    }
}
