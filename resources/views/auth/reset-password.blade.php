{{-- GET /reset-password/{token} (Fortify::resetPasswordView). Posts to Fortify's password.update route. --}}
<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <div class="flex w-full flex-col gap-2 text-center">
            <h1 class="text-xl font-medium dark:text-zinc-200">{{ __('Reset password') }}</h1>
            <p class="text-sm dark:text-zinc-400">{{ __('Please enter your new password below') }}</p>
        </div>

        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-6">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <flux:field>
                <flux:label>{{ __('Email') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:input aria-required="true" name="email" type="email" :value="old('email', $request->string('email')->value())" autocomplete="email" />
                <flux:error name="email" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('New password') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:input aria-required="true" name="password" type="password" autocomplete="new-password" viewable />
                <flux:error name="password" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Confirm password') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:input aria-required="true" name="password_confirmation" type="password" autocomplete="new-password" viewable />
                <flux:error name="password_confirmation" />
            </flux:field>

            <flux:button type="submit" variant="primary" class="w-full">{{ __('Reset password') }}</flux:button>
        </form>
    </div>
</x-layouts.auth>
