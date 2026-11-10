{{-- GET /invitation/{user}?v=…&expires=…&signature=… (InvitationController). No login. --}}
<x-layouts.auth>
    <div class="flex flex-col gap-6">
        @if ($valid)
            <x-auth-header :title="__('Welcome, :name', ['name' => $user->name])" :description="__('Choose a password for your login.')" />

            <form method="POST" action="{{ $actionUrl }}" class="flex flex-col gap-6">
                @csrf
                <x-form.input name="email" type="email" :value="$user->email" :label="__('Email')" readonly autocomplete="username" />
                <x-form.input name="password" type="password" :label="__('Password')" required autocomplete="new-password" viewable />
                <x-form.input name="password_confirmation" type="password" :label="__('Confirm password')" required autocomplete="new-password" viewable />

                <flux:button type="submit" variant="primary" class="w-full">{{ __('Set password and log in') }}</flux:button>
            </form>
        @else
            <x-auth-header :title="__('This link no longer works')"
                :description="__('It was already used, or a newer invitation was sent. If you have set your password, just log in. Otherwise ask HR to send the invitation again.')" />
            <flux:button :href="route('login')" variant="primary" class="w-full">{{ __('Go to login') }}</flux:button>
        @endif
    </div>
</x-layouts.auth>
