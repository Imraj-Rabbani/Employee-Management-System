{{-- GET /login (Fortify::loginView). A plain form: Fortify's POST /login does the work. --}}
<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <x-form.input name="email" type="email" :value="old('email')" :label="__('Email address')" required autofocus autocomplete="email" placeholder="email@example.com" />

            <div class="relative">
                <x-form.input name="password" type="password" :label="__('Password')" required autocomplete="current-password" viewable />

                <x-text-link class="absolute right-0 top-0 text-sm" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </x-text-link>
            </div>

            <flux:checkbox name="remember" :label="__('Remember me')" />

            <flux:button variant="primary" type="submit" class="w-full">{{ __('Log in') }}</flux:button>
        </form>
    </div>
</x-layouts.auth>
