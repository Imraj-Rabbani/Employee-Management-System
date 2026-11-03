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

    public function test_sidebar_shows_self_service_and_module_groups(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard')
            ->assertSeeInOrder(['Self Service', 'HRM', 'Payroll', 'Workflow', 'Setup'])
            ->assertSee('My Payslips')
            ->assertSee('Recruitment')
            ->assertDontSee('livewire-starter-kit');
    }

    public function test_module_group_opens_on_its_own_pages(): void
    {
        $this->actingAs(User::factory()->create());

        // On /hrm/ping only the HRM group is open.
        $html = $this->get('/hrm/ping')->getContent();

        $this->assertSame(1, preg_match_all('/<ui-disclosure[^>]*\sopen\s/', $html));
        $this->assertMatchesRegularExpression('/<ui-disclosure[^>]*\sopen\s[^>]*>.*?HRM/s', $html);
    }

    public function test_topbar_shows_page_title_and_dark_mode_toggle(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Rahim Uddin']));

        $this->get('/hrm/ping')
            ->assertSee('<title>Hrm ping</title>', false)
            ->assertSee('Toggle dark mode')
            ->assertSee('Rahim Uddin');
    }
}
