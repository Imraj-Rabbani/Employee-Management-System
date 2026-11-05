<?php

namespace Tests\Feature\Settings;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Task 2.7c: Settings → Two-factor authentication.
 */
class TwoFactorSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->for(Tenant::factory())->create();
        $this->actingAs($this->user);
    }

    public function test_the_page_asks_for_the_password_first(): void
    {
        $this->get('/settings/two-factor')->assertRedirect(route('password.confirm'));
    }

    public function test_the_page_opens_after_the_password_was_confirmed(): void
    {
        $this->withSession(['auth.password_confirmed_at' => time()])
            ->get('/settings/two-factor')
            ->assertOk()
            ->assertSee('Enable two-factor authentication');
    }

    public function test_enabling_shows_a_qr_code_but_is_not_active_until_confirmed(): void
    {
        Volt::test('settings.two-factor')
            ->call('enable')
            ->assertSeeHtml('data-test="qr-code"')
            ->assertSee('Confirm and turn on');

        $user = $this->user->fresh();
        $this->assertNotNull($user?->two_factor_secret);
        $this->assertFalse($user->hasEnabledTwoFactorAuthentication());
    }

    public function test_a_correct_code_turns_it_on_and_shows_the_recovery_codes(): void
    {
        $page = Volt::test('settings.two-factor')->call('enable');

        $page->set('code', $this->currentCode())
            ->call('confirm')
            ->assertHasNoErrors()
            ->assertSeeHtml('data-test="recovery-codes"')
            ->assertDispatched('toast');

        $this->assertTrue($this->user->fresh()?->hasEnabledTwoFactorAuthentication());
        $this->assertCount(8, $this->user->fresh()?->recoveryCodes() ?? []);
    }

    public function test_a_wrong_code_is_refused(): void
    {
        Volt::test('settings.two-factor')
            ->call('enable')
            ->set('code', '000000')
            ->call('confirm')
            ->assertHasErrors(['code']);

        $this->assertFalse($this->user->fresh()?->hasEnabledTwoFactorAuthentication());
    }

    public function test_an_abandoned_setup_is_reset_when_the_page_is_opened_again(): void
    {
        Volt::test('settings.two-factor')->call('enable');

        Volt::test('settings.two-factor')->assertSee('Enable two-factor authentication');

        $this->assertNull($this->user->fresh()?->two_factor_secret);
    }

    public function test_new_recovery_codes_replace_the_old_ones(): void
    {
        $this->turnOn();
        $old = $this->user->fresh()?->recoveryCodes();

        Volt::test('settings.two-factor')->call('regenerateRecoveryCodes');

        $this->assertNotSame($old, $this->user->fresh()?->recoveryCodes());
    }

    public function test_it_can_be_turned_off(): void
    {
        $this->turnOn();

        Volt::test('settings.two-factor')
            ->assertSee('Disable two-factor authentication')
            ->call('disable')
            ->assertSee('Enable two-factor authentication');

        $user = $this->user->fresh();
        $this->assertFalse($user?->hasEnabledTwoFactorAuthentication());
        $this->assertNull($user?->two_factor_secret);
    }

    public function test_secrets_are_never_in_json(): void
    {
        $this->turnOn();

        $json = $this->user->fresh()?->toJson() ?? '';

        $this->assertStringNotContainsString('two_factor_secret', $json);
        $this->assertStringNotContainsString('two_factor_recovery_codes', $json);
    }

    private function turnOn(): void
    {
        Volt::test('settings.two-factor')->call('enable')->set('code', $this->currentCode())->call('confirm');
    }

    /** The code an authenticator app would show right now. */
    private function currentCode(): string
    {
        return (new Google2FA)->getCurrentOtp(decrypt((string) $this->user->fresh()?->two_factor_secret));
    }
}
