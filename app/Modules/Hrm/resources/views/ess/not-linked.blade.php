<x-layouts.app :title="__('My Profile')">
    <div class="glass overflow-hidden rounded-2xl">
        <div class="p-6">
            <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                    <flux:icon name="user" />
                </div>
                <flux:heading size="lg" class="mt-4">{{ __('Your login is not linked to an employee record') }}</flux:heading>
                <flux:text class="mt-1 max-w-sm">{{ __('Ask HR to link it (Employees → profile → Login).') }}</flux:text>
            </div>
        </div>
    </div>
</x-layouts.app>
