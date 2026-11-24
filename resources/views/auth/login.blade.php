{{-- GET /login (Fortify::loginView). A plain form: Fortify's POST /login does the work. --}}
<x-layouts.auth>
    <div class="flex flex-col gap-5">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ __('Welcome back') }}</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Log in to your account.') }}</p>
        </div>

        @if (session('status'))
            <div class="text-sm font-medium text-green-600">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-4">
            @csrf

            <flux:field>
                <flux:label>{{ __('Email address') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:input aria-required="true" name="email" type="email" :value="old('email')" autofocus autocomplete="email" placeholder="email@example.com" />
                <flux:error name="email" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Password') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:input aria-required="true" name="password" type="password" autocomplete="current-password" viewable />
                <flux:error name="password" />
            </flux:field>

            <div class="flex items-center justify-between">
                <flux:checkbox name="remember" :label="__('Remember me')" />
                <a href="{{ route('password.request') }}" wire:navigate class="text-sm text-zinc-700 underline decoration-neutral-400 underline-offset-2 hover:decoration-neutral-700 dark:text-neutral-200 dark:hover:decoration-neutral-100">{{ __('Forgot your password?') }}</a>
            </div>

            <flux:button variant="primary" type="submit" class="mt-1 w-full">{{ __('Log in') }}</flux:button>
        </form>

        <p class="text-center text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('New to the platform?') }}
            <a href="{{ route('register') }}" wire:navigate class="font-medium text-zinc-900 underline decoration-neutral-400 underline-offset-2 hover:decoration-neutral-700 dark:text-white dark:hover:decoration-neutral-100">{{ __('Register your company') }}</a>
        </p>
    </div>
</x-layouts.auth>
