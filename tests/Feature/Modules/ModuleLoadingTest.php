<?php

namespace Tests\Feature\Modules;

use App\Core\Tenancy\TenantMigrationPaths;
use App\Modules\Hrm\Livewire\OrgChart;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->actingAsTenantUser();

        $this->get('/hrm/org/chart')->assertOk()->assertSeeLivewire(OrgChart::class);
    }
}
