{{-- GET /login (Fortify::loginView). A plain form: Fortify's POST /login does the work. --}}
<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <div class="flex w-full flex-col gap-2 text-center">
            <h1 class="text-xl font-medium dark:text-zinc-200">{{ __('Log in to your account') }}</h1>
            <p class="text-sm dark:text-zinc-400">{{ __('Enter your email and password below to log in') }}</p>
        </div>

        @if (session('status'))
            <div class="text-center text-sm font-medium text-green-600">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <flux:field>
                <flux:label>{{ __('Email address') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:input aria-required="true" name="email" type="email" :value="old('email')" autofocus autocomplete="email" placeholder="email@example.com" />
                <flux:error name="email" />
            </flux:field>

            <div class="relative">
                <flux:field>
                    <flux:label>{{ __('Password') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" name="password" type="password" autocomplete="current-password" viewable />
                    <flux:error name="password" />
                </flux:field>

                <a href="{{ route('password.request') }}" wire:navigate class="underline text-sm decoration-neutral-400 underline-offset-2 duration-300 ease-out hover:decoration-neutral-700 text-neutral-900 dark:text-neutral-200 dark:hover:decoration-neutral-100 absolute right-0 top-0 text-sm">
                    {{ __('Forgot your password?') }}
                </a>
            </div>

            <flux:checkbox name="remember" :label="__('Remember me')" />

            <flux:button variant="primary" type="submit" class="w-full">{{ __('Log in') }}</flux:button>
        </form>

        <div class="text-center text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('New to the platform?') }}
            <a href="{{ route('register') }}" wire:navigate class="underline text-sm decoration-neutral-400 underline-offset-2 duration-300 ease-out hover:decoration-neutral-700 text-neutral-900 dark:text-neutral-200 dark:hover:decoration-neutral-100">{{ __('Register your company') }}</a>
        </div>
    </div>
</x-layouts.auth>
