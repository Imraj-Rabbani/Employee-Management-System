<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use App\Modules\Hrm\Livewire\Ping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ModuleLoadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_migration_folders_are_registered(): void
    {
        $paths = app('migrator')->paths();

        foreach (['Core', 'Workflow', 'Hrm', 'Payroll'] as $module) {
            $this->assertContains(app_path("Modules/{$module}/Database/Migrations"), $paths);
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
        $this->actingAs(User::factory()->create());

        $this->get('/hrm/ping')->assertSeeLivewire(Ping::class);
    }
}
