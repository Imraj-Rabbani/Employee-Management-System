<?php

namespace App\Console\Commands;

use App\Console\Concerns\SelectsTenants;
use App\Core\Tenancy\TenantDatabase;
use App\Core\Tenancy\TenantManager;
use App\Core\Tenancy\TenantMigrationPaths;
use Illuminate\Console\Command;

/**
 * Runs the tenant migrations (app/Modules/STAR/Database/Migrations) in every
 * company's database, or only in the companies given with --tenant.
 *
 *     php artisan tenants:migrate
 *     php artisan tenants:migrate --tenant=demo --tenant=sss
 *     php artisan tenants:migrate --tenant=demo --fresh     (drops all tables first!)
 *     php artisan tenants:migrate --seed                    (then runs tenants:seed)
 */
class TenantsMigrate extends Command
{
    use SelectsTenants;

    protected $signature = 'tenants:migrate
                            {--tenant=* : Only these tenant codes (default: all tenants)}
                            {--fresh : Drop all tables in the tenant database first}
                            {--seed : Run the tenant seeder afterwards}';

    protected $description = 'Run the tenant migrations in each tenant database';

    public function handle(TenantManager $tenants, TenantDatabase $databases, TenantMigrationPaths $paths): int
    {
        // With no paths, "migrate" would fall back to database/migrations: the CENTRAL migrations.
        if ($paths->all() === []) {
            $this->components->warn('No tenant migration folders are registered.');

            return self::FAILURE;
        }

        $list = $this->selectedTenants();
        if ($list->isEmpty()) {
            return self::FAILURE;
        }

        foreach ($list as $tenant) {
            $this->components->info("Tenant [{$tenant->code}] {$tenant->name} → {$tenant->database}");

            if (! $databases->exists($tenant)) {
                $databases->create($tenant);
                $this->components->twoColumnDetail('Database', '<fg=green>created</>');
            }

            $tenants->switchTo($tenant);

            $this->call($this->option('fresh') ? 'migrate:fresh' : 'migrate', [
                '--database' => 'tenant',
                '--path' => $paths->all(),
                '--realpath' => true,
                '--force' => true,
            ]);
        }

        $tenants->forget();

        if ($this->option('seed')) {
            return $this->call('tenants:seed', ['--tenant' => $this->option('tenant')]);
        }

        return self::SUCCESS;
    }
}
