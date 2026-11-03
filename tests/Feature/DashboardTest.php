<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_sidebar_links_to_every_module(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard')
            ->assertSee(route('hrm.ping'))
            ->assertSee(route('payroll.ping'))
            ->assertSee(route('workflow.ping'))
            ->assertSee(route('core.ping'))
            ->assertDontSee('livewire-starter-kit');
    }
}
