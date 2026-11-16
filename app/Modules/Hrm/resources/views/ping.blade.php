<x-layouts.app :title="__('Hrm ping')">
    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ __('Hrm module is alive') }}</flux:heading>
                <flux:text class="mt-1">{{ __('This page comes from app/Modules/Hrm/resources/views/ping.blade.php') }}</flux:text>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <flux:badge>{{ __('Demo') }}</flux:badge>
            </div>
        </div>

        <div class="p-6">
            {{-- A Livewire component that lives inside the Hrm module --}}
            <livewire:hrm::ping />
        </div>

        <div class="border-t border-white/50 px-6 py-4 text-sm text-zinc-600 dark:border-white/10 dark:text-zinc-300">
            {{ __('Buttons above: Livewire counter, toast and confirm modal.') }}
        </div>
    </div>
</x-layouts.app>
