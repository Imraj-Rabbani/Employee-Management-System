<?php

namespace App\Core\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Models\Role;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
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
 * Optional:
 *   - active: false creates the company switched off (its users cannot log in yet)
 *   - then: a function(Tenant $tenant, User $admin) run INSIDE the new company (its database is the
 *     current one) as the last step, e.g. the sign-up page creates the admin's employee record there.
 *     If it fails, the whole company is undone like any other step.
 *
 * codeFor('Acme Garments Ltd') suggests a free company code ("acmegarmentsltd", or "...2" if taken).
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
        bool $active = true,
        ?callable $then = null,
    ): Tenant {
        $code = strtolower(trim($code));

        // Step 1. Fails on a duplicate code (unique index) before anything else happens.
        $tenant = Tenant::create([
            'code' => $code,
            'name' => $name,
            'database' => Tenant::databaseNameFor($code),
            'modules' => $modules,
            'is_active' => $active,
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

            // Step 5. Inside the company's database: the role, then the caller's extra setup.
            $this->insideTenant($tenant, function () use ($tenant, $admin, $then) {
                $role = Role::administrator() ?? throw new RuntimeException('The Administrator role was not seeded.');
                $role->assignTo($admin);

                if ($then !== null) {
                    $then($tenant, $admin);
                }
            });
        } catch (Throwable $e) {
            $this->undo($tenant, $databaseCreated);

            throw $e;
        }

        return $tenant;
    }

    /**
     * A company code that is free (no tenant, no database) made from the company name:
     * lowercase letters and digits, starting with a letter, at most 20 characters.
     */
    public function codeFor(string $companyName): string
    {
        $base = substr((string) preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($companyName))), 0, 20);

        if ($base === '') {
            $base = 'company';
        } elseif (! ctype_alpha($base[0])) {
            $base = 'c'.substr($base, 0, 19);
        }

        $code = $base;
        for ($i = 2; $this->isTaken($code); $i++) {
            $code = $base.$i;
        }

        return $code;
    }

    private function isTaken(string $code): bool
    {
        return Tenant::where('code', $code)->exists()
            || $this->databases->exists(new Tenant(['database' => Tenant::databaseNameFor($code)]));
    }

    /** Company rows (roles, employees...) live in the tenant DB: switch there for a moment, then back to where we were. */
    private function insideTenant(Tenant $tenant, callable $callback): void
    {
        $previous = $this->tenants->current();
        $this->tenants->switchTo($tenant);

        try {
            $callback();
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
