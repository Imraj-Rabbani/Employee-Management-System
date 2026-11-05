<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Volt\Component;

/*
 * Settings → Two-factor authentication (task 2.7c).
 *
 * The page has three states:
 *   off       → "Enable" button
 *   setting up → QR code + "type the 6-digit code from your app" (2FA is NOT active yet)
 *   on        → recovery codes, "new recovery codes", "Disable"
 *
 * The real work is done by Fortify's actions (Enable/Confirm/Disable/GenerateNewRecoveryCodes);
 * this page only calls them. The route has "password.confirm", so the user typed their password recently.
 */
new class extends Component {
    public string $code = '';

    /** Recovery codes are shown right after setup, and later only when the user asks. */
    public bool $showRecoveryCodes = false;

    public function mount(DisableTwoFactorAuthentication $disable): void
    {
        // A setup that was started but never confirmed (page closed halfway): start again from "off".
        if ($this->user()->two_factor_secret && ! $this->user()->two_factor_confirmed_at) {
            $disable($this->user());
        }
    }

    public function enable(EnableTwoFactorAuthentication $enable): void
    {
        $enable($this->user());
        $this->reset('code');
    }

    public function confirm(ConfirmTwoFactorAuthentication $confirm): void
    {
        $this->validate(['code' => ['required', 'digits:6']]);

        $confirm($this->user(), $this->code);   // throws a "code was invalid" error when wrong

        $this->reset('code');
        $this->showRecoveryCodes = true;
        $this->dispatch('toast', message: __('Two-factor authentication is on.'), variant: 'success');
    }

    public function cancelSetup(DisableTwoFactorAuthentication $disable): void
    {
        $disable($this->user());
        $this->reset('code');
    }

    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generate): void
    {
        $generate($this->user());
        $this->showRecoveryCodes = true;
        $this->dispatch('toast', message: __('New recovery codes created. The old ones no longer work.'), variant: 'success');
    }

    public function disable(DisableTwoFactorAuthentication $disable): void
    {
        $disable($this->user());
        $this->showRecoveryCodes = false;
        $this->dispatch('toast', message: __('Two-factor authentication is off.'), variant: 'success');
    }

    public function with(): array
    {
        $user = $this->user();
        $state = match (true) {
            $user->hasEnabledTwoFactorAuthentication() => 'on',
            $user->two_factor_secret !== null => 'setup',
            default => 'off',
        };

        return [
            'state' => $state,
            'qrCode' => $state === 'setup' ? $user->twoFactorQrCodeSvg() : null,
            'setupKey' => $state === 'setup' ? decrypt($user->two_factor_secret) : null,
            'recoveryCodes' => $state === 'on' && $this->showRecoveryCodes ? $user->recoveryCodes() : [],
        ];
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user()->fresh();
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Two-factor authentication')" :subheading="__('Ask for a code from your phone at login, as well as your password.')">
        <div class="space-y-6">
            @if ($state === 'off')
                <div class="flex items-center gap-3">
                    <x-ui.badge color="warning" dot>{{ __('Off') }}</x-ui.badge>
                </div>
                <flux:text>
                    {{ __('When it is on, you need a 6-digit code from an authenticator app (Google Authenticator, Microsoft Authenticator, 1Password…) every time you log in.') }}
                </flux:text>
                <flux:button variant="primary" icon="shield-check" wire:click="enable">{{ __('Enable two-factor authentication') }}</flux:button>

            @elseif ($state === 'setup')
                <flux:text>{{ __('1. Scan this QR code with your authenticator app, or type the setup key.') }}</flux:text>

                <div class="flex flex-wrap items-center gap-6">
                    <div class="rounded-xl bg-white p-3 shadow-sm" data-test="qr-code">{!! $qrCode !!}</div>
                    <div class="space-y-1">
                        <flux:text size="sm">{{ __('Setup key') }}</flux:text>
                        <code class="block rounded-lg bg-zinc-100 px-3 py-2 font-mono text-sm tracking-wider dark:bg-white/10">{{ $setupKey }}</code>
                    </div>
                </div>

                <form wire:submit="confirm" class="space-y-4">
                    <flux:text>{{ __('2. Type the 6-digit code your app shows.') }}</flux:text>
                    <x-form.input wire:model="code" :label="__('Code')" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="max-w-40" autofocus />

                    <div class="flex gap-2">
                        <flux:button type="submit" variant="primary">{{ __('Confirm and turn on') }}</flux:button>
                        <flux:button variant="ghost" wire:click="cancelSetup">{{ __('Cancel') }}</flux:button>
                    </div>
                </form>

            @else
                <div class="flex items-center gap-3">
                    <x-ui.badge color="success" dot>{{ __('On') }}</x-ui.badge>
                    <flux:text>{{ __('You will be asked for a code from your app when you log in.') }}</flux:text>
                </div>

                <x-ui.card :heading="__('Recovery codes')" :subheading="__('If you lose your phone, each code lets you log in once. Keep them somewhere safe, like a password manager.')">
                    @if ($recoveryCodes)
                        <div class="grid grid-cols-2 gap-2 rounded-lg bg-zinc-100 p-4 font-mono text-sm dark:bg-white/10" data-test="recovery-codes">
                            @foreach ($recoveryCodes as $recoveryCode)
                                <div>{{ $recoveryCode }}</div>
                            @endforeach
                        </div>
                    @endif

                    <x-slot:footer>
                        <div class="flex flex-wrap gap-2">
                            @unless ($recoveryCodes)
                                <flux:button size="sm" icon="eye" wire:click="$set('showRecoveryCodes', true)">{{ __('Show recovery codes') }}</flux:button>
                            @endunless
                            <flux:button size="sm" icon="arrow-path" x-on:click="$dispatch('confirm', {
                                title: @js(__('Create new recovery codes?')),
                                message: @js(__('Your current recovery codes will stop working.')),
                                confirmText: @js(__('Create new codes')),
                                onConfirm: () => $wire.regenerateRecoveryCodes(),
                            })">{{ __('New recovery codes') }}</flux:button>
                        </div>
                    </x-slot:footer>
                </x-ui.card>

                <flux:button variant="danger" x-on:click="$dispatch('confirm', {
                    title: @js(__('Turn off two-factor authentication?')),
                    message: @js(__('Your account will only be protected by your password.')),
                    confirmText: @js(__('Turn off')),
                    onConfirm: () => $wire.disable(),
                })">{{ __('Disable two-factor authentication') }}</flux:button>
            @endif
        </div>
    </x-settings.layout>
</section>
