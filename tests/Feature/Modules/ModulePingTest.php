<?php

namespace Tests\Feature\Modules;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class ModulePingTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function modules(): array
    {
        return [
            'core' => ['core'],
            'workflow' => ['workflow'],
            'hrm' => ['hrm'],
            'payroll' => ['payroll'],
        ];
    }

    #[DataProvider('modules')]
    public function test_guests_are_redirected_to_the_login_page(string $prefix): void
    {
        $this->get("/{$prefix}/ping")->assertRedirect('/login');
    }

    #[DataProvider('modules')]
    public function test_module_renders_its_ping_view(string $prefix): void
    {
        $this->actingAsTenantUser();

        $this->get("/{$prefix}/ping")
            ->assertOk()
            ->assertViewIs("{$prefix}::ping")
            ->assertSee(ucfirst($prefix).' module is alive');
    }
}
