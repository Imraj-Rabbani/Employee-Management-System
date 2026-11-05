<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 2.7b: login and logout through Fortify, with our company rules.
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee(route('login.store'))
            ->assertSee('Forgot your password?');
    }

    public function test_company_users_can_log_in_and_land_on_the_start_page(): void
    {
        $user = User::factory()->for(Tenant::factory())->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_super_admins_can_log_in(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_the_email_is_not_case_sensitive(): void
    {
        $user = User::factory()->for(Tenant::factory())->create(['email' => 'rahim@example.com']);

        $this->post('/login', ['email' => 'Rahim@Example.com', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_password_is_refused(): void
    {
        $user = User::factory()->for(Tenant::factory())->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
    }

    public function test_users_of_an_inactive_company_are_refused_with_a_clear_message(): void
    {
        $user = User::factory()->for(Tenant::factory()->state(['is_active' => false]))->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'This company account is inactive.']);

        $this->assertGuest();
    }

    public function test_users_of_an_expired_company_are_refused(): void
    {
        $user = User::factory()->for(Tenant::factory()->state(['expires_at' => now()->subDay()]))->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'This company subscription has expired.']);

        $this->assertGuest();
    }

    public function test_the_login_page_shows_the_message_and_keeps_the_email(): void
    {
        $user = User::factory()->for(Tenant::factory()->state(['is_active' => false]))->create();

        $this->from('/login')
            ->followingRedirects()
            ->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSee('This company account is inactive.')
            ->assertSee('value="'.$user->email.'"', false);
    }

    public function test_users_without_a_company_are_refused(): void
    {
        $user = User::factory()->create(['tenant_id' => null]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Your account is not linked to a company.']);

        $this->assertGuest();
    }

    public function test_the_company_message_is_only_shown_after_a_correct_password(): void
    {
        $user = User::factory()->for(Tenant::factory()->state(['is_active' => false]))->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        $user = User::factory()->for(Tenant::factory())->create();

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertTooManyRequests();
        $this->assertGuest();
    }

    public function test_users_can_log_out(): void
    {
        $this->actingAs(User::factory()->for(Tenant::factory())->create())
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }
}
