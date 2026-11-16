{{-- GET /user/confirm-password (Fortify::confirmPasswordView). Asked before sensitive actions (e.g. 2FA settings). --}}
<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <div class="flex w-full flex-col gap-2 text-center">
            <h1 class="text-xl font-medium dark:text-zinc-200">{{ __('Confirm password') }}</h1>
            <p class="text-sm dark:text-zinc-400">{{ __('This is a secure area of the application. Please confirm your password before continuing.') }}</p>
        </div>

        <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-6">
            @csrf

            <flux:field>
                <flux:label>{{ __('Password') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:input aria-required="true" name="password" type="password" autofocus autocomplete="current-password" viewable />
                <flux:error name="password" />
            </flux:field>

            <flux:button variant="primary" type="submit" class="w-full">{{ __('Confirm') }}</flux:button>
        </form>
    </div>
</x-layouts.auth>
