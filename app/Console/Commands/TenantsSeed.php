<?php

namespace App\Console\Commands;

use App\Console\Concerns\SelectsTenants;
use App\Core\Tenancy\TenantDatabase;
use App\Core\Tenancy\TenantManager;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Console\Command;

/**
 * Fills each company's database with starting data (TenantDatabaseSeeder),
 * or with one seeder given by --class.
 *
 *     php artisan tenants:seed
 *     php artisan tenants:seed --tenant=demo
 *     php artisan tenants:seed --tenant=demo --class=LeaveTypeSeeder
 */
class TenantsSeed extends Command
{
    use SelectsTenants;

    protected $signature = 'tenants:seed
                            {--tenant=* : Only these tenant codes (default: all tenants)}
                            {--class='.TenantDatabaseSeeder::class.' : The seeder class to run}';

    protected $description = 'Seed each tenant database';

    public function handle(TenantManager $tenants, TenantDatabase $databases): int
    {
        $list = $this->selectedTenants();
        if ($list->isEmpty()) {
            return self::FAILURE;
        }

        $result = self::SUCCESS;

        foreach ($list as $tenant) {
            $this->components->info("Tenant [{$tenant->code}] {$tenant->name} → {$tenant->database}");

            // Seeding needs tables, so the database must exist (and be migrated) first.
            if (! $databases->exists($tenant)) {
                $this->components->error('Database does not exist. Run tenants:migrate first.');
                $result = self::FAILURE;

                continue;
            }

            $tenants->switchTo($tenant);

            $this->call('db:seed', [
                '--database' => 'tenant',
                '--class' => $this->option('class'),
                '--force' => true,
            ]);
        }

        $tenants->forget();

        return $result;
    }
}
