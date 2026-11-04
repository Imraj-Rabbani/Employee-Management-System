<?php

namespace Tests\Feature\Tenancy;

use App\Core\Tenancy\TenantDatabase;
use App\Core\Tenancy\TenantManager;
use App\Core\Tenancy\TenantMigrationPaths;
use App\Models\Tenant;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantDatabases;
use Tests\Fixtures\Note;
use Tests\Fixtures\NoteSeeder;
use Tests\TestCase;

class TenantsSeedCommandTest extends TestCase
{
    use CreatesTenantDatabases;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantMigrationPaths::class)->add(base_path('tests/Fixtures/TenantMigrations'));
    }

    public function test_it_seeds_every_tenant_database(): void
    {
        $alpha = $this->createTenantWithDatabase();
        $beta = $this->createTenantWithDatabase();
        $this->artisan('tenants:migrate')->assertSuccessful();

        $this->artisan('tenants:seed', ['--class' => NoteSeeder::class])->assertSuccessful();

        $this->assertSame(1, $this->noteCount($alpha));
        $this->assertSame(1, $this->noteCount($beta));
    }

    public function test_tenant_option_limits_it_to_some_tenants(): void
    {
        $alpha = $this->createTenantWithDatabase();
        $beta = $this->createTenantWithDatabase();
        $this->artisan('tenants:migrate')->assertSuccessful();

        $this->artisan('tenants:seed', ['--tenant' => [$alpha->code], '--class' => NoteSeeder::class])->assertSuccessful();

        $this->assertSame(1, $this->noteCount($alpha));
        $this->assertSame(0, $this->noteCount($beta));
    }

    public function test_the_default_tenant_seeder_runs(): void
    {
        $this->createTenantWithDatabase();
        $this->artisan('tenants:migrate')->assertSuccessful();

        $this->artisan('tenants:seed')->assertSuccessful();
    }

    public function test_migrate_with_seed_option_seeds_too(): void
    {
        $tenant = $this->createTenantWithDatabase();

        // --seed runs the default seeder; point it at the test seeder through the container.
        $this->app->bind(TenantDatabaseSeeder::class, NoteSeeder::class);

        $this->artisan('tenants:migrate', ['--seed' => true])->assertSuccessful();

        $this->assertSame(1, $this->noteCount($tenant));
    }

    public function test_it_fails_for_a_tenant_without_a_database(): void
    {
        $tenant = $this->createTenantWithDatabase();
        app(TenantDatabase::class)->drop($tenant);

        $this->artisan('tenants:seed', ['--tenant' => [$tenant->code]])
            ->expectsOutputToContain('tenants:migrate first')
            ->assertFailed();
    }

    public function test_it_fails_when_no_tenants_match(): void
    {
        $this->artisan('tenants:seed', ['--tenant' => ['nope']])->assertFailed();
    }

    public function test_seeding_does_not_touch_the_central_database(): void
    {
        $this->createTenantWithDatabase();
        $this->artisan('tenants:migrate')->assertSuccessful();

        $this->artisan('tenants:seed', ['--class' => NoteSeeder::class])->assertSuccessful();

        $this->assertSame('central', config('database.default'));
    }

    private function noteCount(Tenant $tenant): int
    {
        app(TenantManager::class)->switchTo($tenant);
        $count = Note::count();
        app(TenantManager::class)->forget();

        return $count;
    }
}
