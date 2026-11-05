<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Task 2.7b: forgot / reset password through Fortify.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $this->get('/forgot-password')->assertOk()->assertSee(route('password.email'));
    }

    public function test_a_reset_link_is_emailed(): void
    {
        Notification::fake();
        $user = User::factory()->for(Tenant::factory())->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status', 'A reset link will be sent if the account exists.');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_an_unknown_email_gets_the_same_answer(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'A reset link will be sent if the account exists.');

        Notification::assertNothingSent();
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();
        $user = User::factory()->for(Tenant::factory())->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) {
            $this->get('/reset-password/'.$notification->token)->assertOk()->assertSee(route('password.update'));

            return true;
        });
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->for(Tenant::factory())->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

            $this->assertTrue(Hash::check('new-password-123', (string) $user->fresh()?->password));

            return true;
        });
    }
}
