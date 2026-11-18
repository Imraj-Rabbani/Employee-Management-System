<div class="flex flex-col gap-6">
    <div class="flex w-full flex-col gap-2 text-center">
        <h1 class="text-xl font-medium dark:text-zinc-200">{{ __('Register your organisation') }}</h1>
        <p class="text-sm dark:text-zinc-400">{{ __('Create your organisation and your administrator login') }}</p>
    </div>

    <form wire:submit="register" class="flex flex-col gap-6">
        <flux:field>
            <flux:label>{{ __('Organisation name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
            <flux:input aria-required="true" wire:model="organisation" autofocus autocomplete="organization" />
            <flux:error name="organisation" />
        </flux:field>

        <flux:separator :text="__('About you')" />

        <flux:field>
            <flux:label>{{ __('Your name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
            <flux:input aria-required="true" wire:model="name" autocomplete="name" />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Your employee ID') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
            <flux:input :placeholder="__('e.g. EMP-001')" aria-required="true" wire:model="employeeCode" />
            <flux:description>{{ __('You will be the first employee.') }}</flux:description>
            <flux:error name="employeeCode" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Email address') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
            <flux:input aria-required="true" wire:model="email" type="email" autocomplete="email" placeholder="email@example.com" />
            <flux:error name="email" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Password') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
            <flux:description>{{ __('At least 8 characters.') }}</flux:description>
            <flux:input aria-required="true" wire:model="password" type="password" autocomplete="new-password" viewable />
            <flux:error name="password" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Confirm password') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
            <flux:input aria-required="true" wire:model="passwordConfirmation" type="password" autocomplete="new-password" viewable />
            <flux:error name="passwordConfirmation" />
        </flux:field>

        <flux:button variant="primary" type="submit" class="w-full">
            <span wire:loading.remove wire:target="register">{{ __('Register') }}</span>
            <span wire:loading wire:target="register">{{ __('Setting up your organisation…') }}</span>
        </flux:button>
    </form>

    <div class="text-center text-sm text-zinc-600 dark:text-zinc-400">
        {{ __('Already have an account?') }}
        <a href="{{ route('login') }}" wire:navigate class="underline text-sm decoration-neutral-400 underline-offset-2 duration-300 ease-out hover:decoration-neutral-700 text-neutral-900 dark:text-neutral-200 dark:hover:decoration-neutral-100">{{ __('Log in') }}</a>
    </div>
</div>
