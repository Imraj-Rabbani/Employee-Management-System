<?php

namespace Tests\Feature\Tenancy;

use App\Core\Tenancy\TenantDatabase;
use App\Core\Tenancy\TenantManager;
use App\Core\Tenancy\TenantMigrationPaths;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesTenantDatabases;
use Tests\TestCase;

class TenantsMigrateCommandTest extends TestCase
{
    use CreatesTenantDatabases;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The real modules have no tenant migrations yet, so add a test one (creates "notes").
        app(TenantMigrationPaths::class)->add(base_path('tests/Fixtures/TenantMigrations'));
    }

    public function test_it_migrates_every_tenant_database(): void
    {
        $alpha = $this->createTenantWithDatabase();
        $beta = $this->createTenantWithDatabase();

        $this->artisan('tenants:migrate')->assertSuccessful();

        $this->assertTrue($this->tenantHasTable($alpha, 'notes'));
        $this->assertTrue($this->tenantHasTable($beta, 'notes'));
        $this->assertFalse(Schema::connection('central')->hasTable('notes'));
    }

    public function test_tenant_option_limits_it_to_some_tenants(): void
    {
        $alpha = $this->createTenantWithDatabase();
        $beta = $this->createTenantWithDatabase();

        $this->artisan('tenants:migrate', ['--tenant' => [$alpha->code]])->assertSuccessful();

        $this->assertTrue($this->tenantHasTable($alpha, 'notes'));
        $this->assertFalse($this->tenantHasTable($beta, 'notes'));
    }

    public function test_it_creates_a_missing_tenant_database(): void
    {
        $tenant = $this->createTenantWithDatabase();
        app(TenantDatabase::class)->drop($tenant);

        $this->artisan('tenants:migrate', ['--tenant' => [$tenant->code]])
            ->expectsOutputToContain('created')
            ->assertSuccessful();

        $this->assertTrue($this->tenantHasTable($tenant, 'notes'));
    }

    public function test_running_twice_is_safe(): void
    {
        $tenant = $this->createTenantWithDatabase();

        $this->artisan('tenants:migrate')->assertSuccessful();
        $this->artisan('tenants:migrate')->expectsOutputToContain('Nothing to migrate')->assertSuccessful();

        $this->assertTrue($this->tenantHasTable($tenant, 'notes'));
    }

    public function test_it_fails_when_no_tenants_match(): void
    {
        $this->artisan('tenants:migrate', ['--tenant' => ['nope']])->assertFailed();
    }

    public function test_central_migrate_does_not_run_tenant_migrations(): void
    {
        $this->assertNotContains(base_path('tests/Fixtures/TenantMigrations'), app('migrator')->paths());
    }

    private function tenantHasTable(Tenant $tenant, string $table): bool
    {
        app(TenantManager::class)->switchTo($tenant);
        $has = Schema::connection('tenant')->hasTable($table);
        app(TenantManager::class)->forget();

        return $has;
    }
}
