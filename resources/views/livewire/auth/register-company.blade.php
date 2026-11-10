<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Register your organisation')" :description="__('Create your organisation and your administrator login')" />

    <form wire:submit="register" class="flex flex-col gap-6">
        <x-form.input wire:model="organisation" :label="__('Organisation name')" required autofocus autocomplete="organization" />

        <flux:separator :text="__('About you')" />

        <x-form.input wire:model="name" :label="__('Your name')" required autocomplete="name" />
        <x-form.input wire:model="employeeCode" :label="__('Your employee ID')" :hint="__('e.g. EMP-001. You will be the first employee.')" required />
        <x-form.input wire:model="email" type="email" :label="__('Email address')" required autocomplete="email" placeholder="email@example.com" />
        <x-form.input wire:model="password" type="password" :label="__('Password')" :hint="__('At least 8 characters.')" required autocomplete="new-password" viewable />
        <x-form.input wire:model="passwordConfirmation" type="password" :label="__('Confirm password')" required autocomplete="new-password" viewable />

        <flux:button variant="primary" type="submit" class="w-full">
            <span wire:loading.remove wire:target="register">{{ __('Register') }}</span>
            <span wire:loading wire:target="register">{{ __('Setting up your organisation…') }}</span>
        </flux:button>
    </form>

    <div class="text-center text-sm text-zinc-600 dark:text-zinc-400">
        {{ __('Already have an account?') }}
        <x-text-link :href="route('login')">{{ __('Log in') }}</x-text-link>
    </div>
</div>
