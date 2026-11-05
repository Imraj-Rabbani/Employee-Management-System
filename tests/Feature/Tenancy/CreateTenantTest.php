<?php

namespace Tests\Feature\Tenancy;

use App\Core\Tenancy\CreateTenant;
use App\Core\Tenancy\TenantDatabase;
use App\Core\Tenancy\TenantManager;
use App\Core\Tenancy\TenantMigrationPaths;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Task 2.6b: CreateTenant sets up a whole company, or nothing at all.
 * Uses real databases; tearDown() drops everything it made.
 */
class CreateTenantTest extends TestCase
{
    use RefreshDatabase;

    private string $code;

    protected function setUp(): void
    {
        parent::setUp();

        // The real modules have no tenant migrations yet, so add a test one (creates "notes").
        app(TenantMigrationPaths::class)->add(base_path('tests/Fixtures/TenantMigrations'));

        $this->code = 't'.strtolower(str()->random(8));
    }

    protected function tearDown(): void
    {
        // CREATE DATABASE commits the test transaction, so clean up by hand (like CreatesTenantDatabases).
        app(TenantManager::class)->forget();
        $databases = app(TenantDatabase::class);

        foreach (Tenant::where('code', 'like', $this->code.'%')->get() as $tenant) {
            $databases->drop($tenant);
            $tenant->users()->delete();
            $tenant->delete();
        }

        $leftover = new Tenant(['database' => Tenant::databaseNameFor($this->code)]);
        $databases->drop($leftover);

        User::where('email', 'like', '%@'.$this->code.'.test')->delete();

        parent::tearDown();
    }

    public function test_it_creates_the_tenant_its_database_tables_and_admin_login(): void
    {
        $tenant = $this->createTenant(modules: ['hrm', 'payroll']);

        // 1. Central row
        $this->assertSame('ems_tenant_'.$this->code, $tenant->database);
        $this->assertSame(['hrm', 'payroll'], $tenant->fresh()?->modules);
        $this->assertTrue($tenant->is_active);

        // 2 + 3. Database exists and is migrated
        $this->assertTrue(app(TenantDatabase::class)->exists($tenant));
        app(TenantManager::class)->switchTo($tenant);
        $this->assertTrue(Schema::connection('tenant')->hasTable('notes'));

        // 4. Admin login belongs to the company, password is hashed
        $admin = User::where('email', 'admin@'.$this->code.'.test')->firstOrFail();
        $this->assertSame($tenant->id, $admin->tenant_id);
        $this->assertFalse($admin->isSuperAdmin());
        $this->assertTrue(Hash::check('secret-password', $admin->password));
    }

    public function test_the_code_is_saved_in_lowercase(): void
    {
        $tenant = $this->createTenant(code: strtoupper($this->code));

        $this->assertSame($this->code, $tenant->code);
    }

    public function test_everything_is_undone_when_a_later_step_fails(): void
    {
        // The admin email is already taken, so step 4 fails after the DB was created.
        User::factory()->create(['email' => 'admin@'.$this->code.'.test']);

        try {
            $this->createTenant();
            $this->fail('CreateTenant should have failed.');
        } catch (QueryException) {
            // expected: duplicate email
        }

        $this->assertNull(Tenant::where('code', $this->code)->first());
        $this->assertFalse(app(TenantDatabase::class)->exists(new Tenant(['database' => 'ems_tenant_'.$this->code])));
    }

    public function test_it_never_reuses_or_drops_an_existing_database(): void
    {
        // A leftover database with the same name, e.g. from a deleted company.
        $leftover = new Tenant(['database' => Tenant::databaseNameFor($this->code)]);
        app(TenantDatabase::class)->create($leftover);

        try {
            $this->createTenant();
            $this->fail('CreateTenant should have refused the existing database.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('already exists', $e->getMessage());
        }

        $this->assertNull(Tenant::where('code', $this->code)->first());
        $this->assertTrue(app(TenantDatabase::class)->exists($leftover), 'The existing database must not be dropped.');
    }

    public function test_a_duplicate_code_is_refused_without_touching_the_first_company(): void
    {
        $first = $this->createTenant();

        try {
            $this->createTenant(adminEmail: 'other@'.$this->code.'.test');
            $this->fail('A duplicate code should be refused.');
        } catch (QueryException) {
            // expected: unique index on tenants.code
        }

        $this->assertTrue(app(TenantDatabase::class)->exists($first));
        $this->assertSame(1, Tenant::where('code', $this->code)->count());
    }

    /** @param  list<string>  $modules */
    private function createTenant(?string $code = null, ?string $adminEmail = null, array $modules = []): Tenant
    {
        return app(CreateTenant::class)->handle(
            code: $code ?? $this->code,
            name: 'Test Company',
            adminName: 'Company Admin',
            adminEmail: $adminEmail ?? 'admin@'.$this->code.'.test',
            adminPassword: 'secret-password',
            modules: $modules,
        );
    }
}
