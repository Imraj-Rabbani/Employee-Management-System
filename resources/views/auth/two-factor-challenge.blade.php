{{--
    GET /two-factor-challenge (Fortify::twoFactorChallengeView).
    Shown after a correct password when the user has 2FA on. Posts to Fortify's two-factor.login.store route
    with either "code" (from the app) or "recovery_code" (one of the saved backup codes).
--}}
<x-layouts.auth>
    {{-- Opens on the recovery-code form again if that is the one that just failed. --}}
    <div class="flex flex-col gap-6" x-data="{ recovery: @js($errors->has('recovery_code')) }">
        <div x-show="! recovery">
            <x-auth-header :title="__('Two-factor authentication')" :description="__('Enter the 6-digit code from your authenticator app.')" />
        </div>
        <div x-show="recovery" x-cloak>
            <x-auth-header :title="__('Use a recovery code')" :description="__('Enter one of the recovery codes you saved when you turned on two-factor authentication.')" />
        </div>

        <form method="POST" action="{{ route('two-factor.login.store') }}" class="flex flex-col gap-6">
            @csrf

            {{-- Only the visible field is sent: the hidden one is disabled. --}}
            <div x-show="! recovery">
                <x-form.input name="code" :label="__('Code')" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus x-bind:disabled="recovery" />
            </div>
            <div x-show="recovery" x-cloak>
                <x-form.input name="recovery_code" :label="__('Recovery code')" autocomplete="off" x-bind:disabled="! recovery" />
            </div>

            <flux:button variant="primary" type="submit" class="w-full">{{ __('Log in') }}</flux:button>
        </form>

        <div class="text-center text-sm">
            <button type="button" class="text-zinc-600 underline underline-offset-4 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white" x-on:click="recovery = ! recovery">
                <span x-show="! recovery">{{ __('Lost your phone? Use a recovery code') }}</span>
                <span x-show="recovery" x-cloak>{{ __('Use the code from your app') }}</span>
            </button>
        </div>
    </div>
</x-layouts.auth>
