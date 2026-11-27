<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "/" shows guests the landing page and sends signed-in users to their start page.
 */
class StartPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_the_landing_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertViewIs('landing')
            ->assertSee(route('login'));
    }

    public function test_company_users_go_to_their_dashboard(): void
    {
        $this->actingAs(User::factory()->for(Tenant::factory())->create())
            ->get('/')
            ->assertRedirect(route('dashboard'));
    }

    public function test_super_admins_go_to_platform_admin(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/')
            ->assertRedirect(route('admin.dashboard'));
    }
}
