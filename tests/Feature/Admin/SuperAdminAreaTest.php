<?php

namespace Tests\Feature\Admin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 2.6a: only super admins can open the /admin area.
 */
class SuperAdminAreaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_super_admin_can_open_the_admin_area(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin')
            ->assertOk()
            ->assertSee('Platform admin');
    }

    public function test_a_company_user_gets_403(): void
    {
        $this->actingAs(User::factory()->for(Tenant::factory())->create())
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_a_user_without_a_company_is_not_automatically_a_super_admin(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => null]))
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_the_sidebar_link_shows_only_for_super_admins(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/dashboard')
            ->assertSee(route('admin.dashboard'));

        $this->actingAs(User::factory()->for(Tenant::factory())->create())
            ->get('/dashboard')
            ->assertDontSee(route('admin.dashboard'));
    }

    public function test_the_flag_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->create();

        $user->fill(['is_super_admin' => true])->save();

        $this->assertFalse($user->fresh()?->isSuperAdmin());
    }
}
