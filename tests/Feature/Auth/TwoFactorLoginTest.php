<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Task 2.7d: logging in when two-factor authentication is on.
 */
class TwoFactorLoginTest extends TestCase
{
    use RefreshDatabase;

    private string $secret;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secret = (new Google2FA)->generateSecretKey();
        $this->user = User::factory()->for(Tenant::factory())->create();
        $this->turnOnTwoFactor($this->user);
    }

    public function test_after_the_password_the_user_must_enter_a_code(): void
    {
        $this->post('/login', ['email' => $this->user->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.login'));

        $this->assertGuest();   // not logged in yet!

        $this->get('/two-factor-challenge')
            ->assertOk()
            ->assertSee(route('two-factor.login.store'))
            ->assertSee('Use a recovery code');
    }

    public function test_the_code_from_the_app_logs_the_user_in(): void
    {
        $this->post('/login', ['email' => $this->user->email, 'password' => 'password']);

        $this->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($this->secret)])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($this->user);
    }

    public function test_a_wrong_code_is_refused(): void
    {
        $this->post('/login', ['email' => $this->user->email, 'password' => 'password']);

        $this->post('/two-factor-challenge', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_a_recovery_code_works_once(): void
    {
        $this->post('/login', ['email' => $this->user->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['recovery_code' => 'recovery-one'])->assertRedirect('/');
        $this->assertAuthenticatedAs($this->user);

        $this->assertNotContains('recovery-one', $this->user->fresh()?->recoveryCodes() ?? [], 'A used code is replaced.');

        // Log out and try the same recovery code again.
        $this->post('/logout');
        $this->post('/login', ['email' => $this->user->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['recovery_code' => 'recovery-one'])->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }

    public function test_the_challenge_page_cannot_be_opened_without_a_correct_password_first(): void
    {
        $this->get('/two-factor-challenge')->assertRedirect(route('login'));
    }

    public function test_company_rules_still_apply_before_the_code_is_asked(): void
    {
        $this->user->tenant?->update(['is_active' => false]);

        $this->post('/login', ['email' => $this->user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'This company account is inactive.']);

        $this->get('/two-factor-challenge')->assertRedirect(route('login'));
    }

    public function test_users_without_two_factor_log_in_directly(): void
    {
        $other = User::factory()->for(Tenant::factory())->create();

        $this->post('/login', ['email' => $other->email, 'password' => 'password'])->assertRedirect('/');

        $this->assertAuthenticatedAs($other);
    }

    private function turnOnTwoFactor(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => encrypt($this->secret),
            'two_factor_recovery_codes' => encrypt((string) json_encode(['recovery-one', 'recovery-two'])),
            'two_factor_confirmed_at' => now(),
        ])->save();
    }
}
