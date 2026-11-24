{{-- GET /invitation/{user}?v=…&expires=…&signature=… (InvitationController). No login. --}}
<x-layouts.auth>
    <div class="flex flex-col gap-6">
        @if ($valid)
            <div class="flex w-full flex-col gap-2 text-center">
                <h1 class="text-xl font-medium dark:text-zinc-200">{{ __('Welcome, :name', ['name' => $user->name]) }}</h1>
                <p class="text-sm dark:text-zinc-400">{{ __('Choose a password for your login.') }}</p>
            </div>

            <form method="POST" action="{{ $actionUrl }}" class="flex flex-col gap-6">
                @csrf
                <flux:field>
                    <flux:label>{{ __('Email') }}</flux:label>
                    <flux:input name="email" type="email" :value="$user->email" readonly autocomplete="username" />
                    <flux:error name="email" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Password') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" name="password" type="password" autocomplete="new-password" viewable />
                    <flux:error name="password" />
                </flux:field>

                <flux:button type="submit" variant="primary" class="w-full">{{ __('Set password and log in') }}</flux:button>
            </form>
        @else
            <div class="flex w-full flex-col gap-2 text-center">
                <h1 class="text-xl font-medium dark:text-zinc-200">{{ __('This link no longer works') }}</h1>
                <p class="text-sm dark:text-zinc-400">{{ __('It was already used, or a newer invitation was sent. If you have set your password, just log in. Otherwise ask HR to send the invitation again.') }}</p>
            </div>
            <flux:button :href="route('login')" variant="primary" class="w-full">{{ __('Go to login') }}</flux:button>
        @endif
    </div>
</x-layouts.auth>
