<?php

namespace App\Core\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Models\Role;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Throwable;

/**
 * Sets up a new company in one call:
 *
 *   1. saves the tenant row in the central DB (ems_clients.tenants)
 *   2. creates its database ems_tenant_{code}
 *   3. runs the tenant migrations and TenantDatabaseSeeder in it
 *   4. creates the company's first login (admin user) in the central users table,
 *      and gives it the company's Administrator role (seeded in step 3)
 *
 *     $tenant = app(CreateTenant::class)->handle(
 *         code: 'sss', name: 'SSS Ltd',
 *         adminName: 'Rahim', adminEmail: 'rahim@sss.com', adminPassword: 'secret123',
 *         modules: ['hrm', 'payroll'],
 *     );
 *
 * If any step fails, what was already done is undone and the error is thrown again.
 * (A DB transaction can't do this for us: MySQL commits on CREATE DATABASE.)
 *
 * Input is NOT validated here (that is the form's job, task 2.6c), except what keeps
 * the system safe: the code must give a valid database name, and an existing database is never reused.
 */
class CreateTenant
{
    public function __construct(
        private TenantDatabase $databases,
        private TenantMigrationPaths $migrationPaths,
        private TenantManager $tenants,
    ) {}

    /** @param  list<string>  $modules */
    public function handle(
        string $code,
        string $name,
        string $adminName,
        string $adminEmail,
        string $adminPassword,
        array $modules = [],
    ): Tenant {
        $code = strtolower(trim($code));

        // Step 1. Fails on a duplicate code (unique index) before anything else happens.
        $tenant = Tenant::create([
            'code' => $code,
            'name' => $name,
            'database' => Tenant::databaseNameFor($code),
            'modules' => $modules,
            'is_active' => true,
        ]);

        $databaseCreated = false;

        try {
            // Step 2. Never take over a database that is already there: it may hold another company's data.
            if ($this->databases->exists($tenant)) {
                throw new RuntimeException("Database [{$tenant->database}] already exists. Choose another code or remove the old database.");
            }

            $this->databases->create($tenant);
            $databaseCreated = true;

            // Step 3. Reuse the Artisan commands from task 2.3.
            if ($this->migrationPaths->all() !== []) {
                $this->artisan('tenants:migrate', ['--tenant' => [$code]]);
            }
            $this->artisan('tenants:seed', ['--tenant' => [$code]]);

            // Step 4. The company's first login, with the Administrator role.
            $admin = $tenant->users()->create([
                'name' => $adminName,
                'email' => $adminEmail,
                'password' => $adminPassword,   // hashed by the User model's "hashed" cast
            ]);
            $this->makeAdministrator($tenant, $admin);
        } catch (Throwable $e) {
            $this->undo($tenant, $databaseCreated);

            throw $e;
        }

        return $tenant;
    }

    /** Role rows live in the tenant DB: switch there for a moment, then back to where we were. */
    private function makeAdministrator(Tenant $tenant, User $user): void
    {
        $previous = $this->tenants->current();
        $this->tenants->switchTo($tenant);

        try {
            $role = Role::administrator() ?? throw new RuntimeException('The Administrator role was not seeded.');
            $role->assignTo($user);
        } finally {
            $previous !== null ? $this->tenants->switchTo($previous) : $this->tenants->forget();
        }
    }

    /** @param  array<string, mixed>  $parameters */
    private function artisan(string $command, array $parameters): void
    {
        if (Artisan::call($command, $parameters) !== 0) {
            throw new RuntimeException("[{$command}] failed: ".trim(Artisan::output()));
        }
    }

    private function undo(Tenant $tenant, bool $databaseCreated): void
    {
        if ($databaseCreated) {
            $this->databases->drop($tenant);
        }

        User::where('tenant_id', $tenant->id)->delete();
        $tenant->delete();
    }
}
