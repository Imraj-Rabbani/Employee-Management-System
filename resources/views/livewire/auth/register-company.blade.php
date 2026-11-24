<div class="flex flex-col gap-5">
    <div>
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ __('Register your organisation') }}</h1>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Create your organisation and your administrator login.') }}</p>
    </div>

    <form wire:submit="register" class="flex flex-col gap-4">
        {{-- Plan: two cards, the chosen one is highlighted. Payment for Business comes later. --}}
        <fieldset>
            <legend class="mb-2 text-sm font-medium text-zinc-800 dark:text-white">{{ __('Plan') }}</legend>
            <div class="grid grid-cols-2 gap-3">
                @foreach ($plans as $option)
                    <label wire:key="plan-{{ $option->value }}" @class([
                        'flex cursor-pointer flex-col rounded-xl border px-3 py-2 transition',
                        'border-brand-700 bg-brand-50 ring-1 ring-brand-700 dark:border-brand-400 dark:bg-brand-900/40 dark:ring-brand-400' => $plan === $option->value,
                        'border-zinc-200 hover:border-zinc-400 dark:border-white/15 dark:hover:border-white/30' => $plan !== $option->value,
                    ])>
                        <input type="radio" wire:model.live="plan" value="{{ $option->value }}" class="sr-only">
                        <span class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $option->label() }}</span>
                        <span class="text-xs text-zinc-600 dark:text-zinc-400">{{ __(':count employees · :gb GB', ['count' => $option->maxEmployees(), 'gb' => $option->storageGb()]) }}</span>
                    </label>
                @endforeach
            </div>
            <flux:error name="plan" />
        </fieldset>

        <flux:field>
            <flux:label>{{ __('Organisation name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
            <flux:input aria-required="true" wire:model="organisation" autofocus autocomplete="organization" />
            <flux:error name="organisation" />
        </flux:field>

        <div class="grid grid-cols-2 gap-4">
            <flux:field>
                <flux:label>{{ __('First name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:input aria-required="true" wire:model="firstName" autocomplete="given-name" />
                <flux:error name="firstName" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Last name') }}</flux:label>
                <flux:input wire:model="lastName" autocomplete="family-name" />
                <flux:error name="lastName" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>{{ __('Email address') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
            <flux:input aria-required="true" wire:model="email" type="email" autocomplete="email" placeholder="email@example.com" />
            <flux:error name="email" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Password') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
            <flux:input aria-required="true" wire:model="password" type="password" autocomplete="new-password" :placeholder="__('At least 8 characters')" viewable />
            <flux:error name="password" />
        </flux:field>

        <flux:button variant="primary" type="submit" class="mt-1 w-full">
            <span wire:loading.remove wire:target="register">{{ __('Register') }}</span>
            <span wire:loading wire:target="register">{{ __('Setting up your organisation…') }}</span>
        </flux:button>
    </form>

    <p class="text-center text-sm text-zinc-600 dark:text-zinc-400">
        {{ __('Already have an account?') }}
        <a href="{{ route('login') }}" wire:navigate class="font-medium text-zinc-900 underline decoration-neutral-400 underline-offset-2 hover:decoration-neutral-700 dark:text-white dark:hover:decoration-neutral-100">{{ __('Log in') }}</a>
    </p>
</div>
