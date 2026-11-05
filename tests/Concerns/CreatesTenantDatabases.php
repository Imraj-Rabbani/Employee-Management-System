<?php

namespace Tests\Concerns;

use App\Core\Tenancy\TenantDatabase;
use App\Core\Tenancy\TenantManager;
use App\Models\Tenant;
use Closure;

/**
 * For tests that need REAL tenant databases.
 *
 * MySQL commits the open transaction on CREATE/DROP DATABASE, so RefreshDatabase
 * cannot undo the rows these tests create. tearDownCreatesTenantDatabases() cleans
 * up instead: it drops every database made here and deletes the tenants and their users.
 * (Laravel calls setUp{TraitName}/tearDown{TraitName} methods of test traits automatically.)
 */
trait CreatesTenantDatabases
{
    /** @var list<Tenant> */
    private array $createdTenants = [];

    /**
     * Create a tenant with its own database. $schema builds tables in it, e.g.
     * fn () => Schema::connection('tenant')->create('notes', ...).
     */
    protected function createTenantWithDatabase(?Closure $schema = null): Tenant
    {
        $tenant = Tenant::factory()->create(['code' => 't'.strtolower(str()->random(8))]);
        $this->createdTenants[] = $tenant;

        app(TenantDatabase::class)->create($tenant);

        if ($schema) {
            app(TenantManager::class)->switchTo($tenant);
            $schema();
            app(TenantManager::class)->forget();
        }

        return $tenant;
    }

    /**
     * Create a tenant with its own database, ALL real tenant migrations run (settings, roles...)
     * and the starting data seeded (e.g. the Administrator role).
     *
     * @param  array<string, mixed>  $attributes  tenant attributes, e.g. ['modules' => ['hrm']]
     */
    protected function createMigratedTenant(array $attributes = []): Tenant
    {
        $tenant = $this->createTenantWithDatabase();
        if ($attributes !== []) {
            $tenant->update($attributes);
        }

        $this->artisan('tenants:migrate', ['--tenant' => [$tenant->code], '--seed' => true])->assertSuccessful();

        return $tenant;
    }

    protected function tearDownCreatesTenantDatabases(): void
    {
        app(TenantManager::class)->forget();

        foreach ($this->createdTenants as $tenant) {
            app(TenantDatabase::class)->drop($tenant);
            $tenant->users()->delete();
            $tenant->delete();
        }

        $this->createdTenants = [];
    }
}
