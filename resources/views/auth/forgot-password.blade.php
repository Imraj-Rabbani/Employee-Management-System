{{-- GET /forgot-password (Fortify::requestPasswordResetLinkView). Posts to Fortify's password.email route. --}}
<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Forgot password')" :description="__('Enter your email to receive a password reset link')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-6">
            @csrf

            <x-form.input name="email" type="email" :value="old('email')" :label="__('Email address')" required autofocus placeholder="email@example.com" />

            <flux:button variant="primary" type="submit" class="w-full">{{ __('Email password reset link') }}</flux:button>
        </form>

        <div class="space-x-1 text-center text-sm text-zinc-500">
            {{ __('Or, return to') }}
            <x-text-link href="{{ route('login') }}">{{ __('log in') }}</x-text-link>
        </div>
    </div>
</x-layouts.auth>
