{{-- GET /user/confirm-password (Fortify::confirmPasswordView). Asked before sensitive actions (e.g. 2FA settings). --}}
<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Confirm password')" :description="__('This is a secure area of the application. Please confirm your password before continuing.')" />

        <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-6">
            @csrf

            <x-form.input name="password" type="password" :label="__('Password')" required autofocus autocomplete="current-password" viewable />

            <flux:button variant="primary" type="submit" class="w-full">{{ __('Confirm') }}</flux:button>
        </form>
    </div>
</x-layouts.auth>
