{{-- GET /reset-password/{token} (Fortify::resetPasswordView). Posts to Fortify's password.update route. --}}
<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Reset password')" :description="__('Please enter your new password below')" />

        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-6">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <x-form.input name="email" type="email" :value="old('email', $request->string('email')->value())" :label="__('Email')" required autocomplete="email" />
            <x-form.input name="password" type="password" :label="__('New password')" required autocomplete="new-password" viewable />
            <x-form.input name="password_confirmation" type="password" :label="__('Confirm password')" required autocomplete="new-password" viewable />

            <flux:button type="submit" variant="primary" class="w-full">{{ __('Reset password') }}</flux:button>
        </form>
    </div>
</x-layouts.auth>
