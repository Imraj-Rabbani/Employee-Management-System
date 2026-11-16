{{-- GET /forgot-password (Fortify::requestPasswordResetLinkView). Posts to Fortify's password.email route. --}}
<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <div class="flex w-full flex-col gap-2 text-center">
            <h1 class="text-xl font-medium dark:text-zinc-200">{{ __('Forgot password') }}</h1>
            <p class="text-sm dark:text-zinc-400">{{ __('Enter your email to receive a password reset link') }}</p>
        </div>

        @if (session('status'))
            <div class="text-center text-sm font-medium text-green-600">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-6">
            @csrf

            <flux:field>
                <flux:label>{{ __('Email address') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:input aria-required="true" name="email" type="email" :value="old('email')" autofocus placeholder="email@example.com" />
                <flux:error name="email" />
            </flux:field>

            <flux:button variant="primary" type="submit" class="w-full">{{ __('Email password reset link') }}</flux:button>
        </form>

        <div class="space-x-1 text-center text-sm text-zinc-500">
            {{ __('Or, return to') }}
            <a href="{{ route('login') }}" wire:navigate class="underline text-sm decoration-neutral-400 underline-offset-2 duration-300 ease-out hover:decoration-neutral-700 text-neutral-900 dark:text-neutral-200 dark:hover:decoration-neutral-100">{{ __('log in') }}</a>
        </div>
    </div>
</x-layouts.auth>
