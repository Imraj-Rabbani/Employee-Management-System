<?php

namespace Tests\Feature\Modules;

use App\Core\Tenancy\TenantMigrationPaths;
use App\Modules\Hrm\Livewire\Ping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class ModuleLoadingTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    public function test_module_migration_folders_are_tenant_migrations_only(): void
    {
        $tenantPaths = app(TenantMigrationPaths::class)->all();
        $centralPaths = app('migrator')->paths();

        foreach (['Core', 'Workflow', 'Hrm', 'Payroll'] as $module) {
            $this->assertContains(app_path("Modules/{$module}/Database/Migrations"), $tenantPaths);
            $this->assertNotContains(app_path("Modules/{$module}/Database/Migrations"), $centralPaths);
        }
    }

    public function test_module_livewire_component_resolves_by_namespace(): void
    {
        Livewire::test('hrm::ping')
            ->assertSee('Clicked 0 times')
            ->call('increment')
            ->assertSee('Clicked 1 times');
    }

    public function test_ping_page_renders_the_livewire_component(): void
    {
        $this->actingAsTenantUser();

        $this->get('/hrm/ping')->assertSeeLivewire(Ping::class);
    }
}
