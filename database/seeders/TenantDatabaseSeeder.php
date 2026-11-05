<?php

namespace Database\Seeders;

use App\Modules\Core\Database\Seeders\RolesSeeder;
use Illuminate\Database\Seeder;

/**
 * Starting data for every company database. Run with: php artisan tenants:seed
 *
 * (DatabaseSeeder next to this file is for the CENTRAL database.)
 * Runs on the "tenant" connection, so TenantModel classes and DB::table() write
 * into the current company's database. Keep seeders safe to run twice
 * (use firstOrCreate / updateOrCreate), because tenants:seed may be re-run.
 */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            // Added as the modules are built, e.g.:
            // \App\Modules\Hrm\Database\Seeders\LeaveTypeSeeder::class,      (Phase 5)
        ]);
    }
}
