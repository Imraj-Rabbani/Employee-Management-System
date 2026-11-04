<?php

namespace Tests\Feature\Tenancy;

use App\Core\Tenancy\TenantDatabase;
use App\Core\Tenancy\TenantMigrationPaths;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTenantDatabases;
use Tests\TestCase;

class TenantsRunCommandTest extends TestCase
{
    use CreatesTenantDatabases;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantMigrationPaths::class)->add(base_path('tests/Fixtures/TenantMigrations'));

        // A test command that prints which database it is talking to, and takes an option.
        Artisan::command('test:whoami {--shout}', function () {
            $db = DB::connection()->getDatabaseName();
            $this->line('db='.($this->option('shout') ? strtoupper($db) : $db));
        });
    }

    public function test_it_runs_the_command_once_per_tenant_on_its_database(): void
    {
        $alpha = $this->createTenantWithDatabase();
        $beta = $this->createTenantWithDatabase();

        $this->artisan('tenants:run', ['line' => 'test:whoami'])
            ->expectsOutputToContain('db='.$alpha->database)
            ->expectsOutputToContain('db='.$beta->database)
            ->assertSuccessful();
    }

    public function test_options_inside_the_quotes_are_passed_on(): void
    {
        $tenant = $this->createTenantWithDatabase();

        $this->artisan('tenants:run', ['line' => 'test:whoami --shout'])
            ->expectsOutputToContain('db='.strtoupper($tenant->database))
            ->assertSuccessful();
    }

    public function test_tenant_option_limits_it_to_some_tenants(): void
    {
        $alpha = $this->createTenantWithDatabase();
        $beta = $this->createTenantWithDatabase();

        $this->artisan('tenants:run', ['line' => 'test:whoami', '--tenant' => [$alpha->code]])
            ->expectsOutputToContain('db='.$alpha->database)
            ->doesntExpectOutputToContain('db='.$beta->database)
            ->assertSuccessful();
    }

    public function test_central_is_the_default_connection_again_afterwards(): void
    {
        $this->createTenantWithDatabase();

        $this->artisan('tenants:run', ['line' => 'test:whoami'])->assertSuccessful();

        $this->assertSame('central', DB::getDefaultConnection());
    }

    public function test_unknown_commands_are_rejected(): void
    {
        $this->createTenantWithDatabase();

        $this->artisan('tenants:run', ['line' => 'nope:nothing'])
            ->expectsOutputToContain('does not exist')
            ->assertFailed();
    }

    public function test_migrate_and_tenants_commands_are_blocked(): void
    {
        $this->createTenantWithDatabase();

        foreach (['migrate', 'migrate:fresh', 'db:wipe', 'tenants:seed'] as $line) {
            $this->artisan('tenants:run', ['line' => $line])
                ->expectsOutputToContain('cannot be run with tenants:run')
                ->assertFailed();
        }
    }

    public function test_it_fails_for_a_tenant_without_a_database(): void
    {
        $tenant = $this->createTenantWithDatabase();
        app(TenantDatabase::class)->drop($tenant);

        $this->artisan('tenants:run', ['line' => 'test:whoami', '--tenant' => [$tenant->code]])
            ->expectsOutputToContain('tenants:migrate first')
            ->assertFailed();
    }
}
