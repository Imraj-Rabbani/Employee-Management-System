<x-layouts.app :title="__('Hrm ping')">
    <x-ui.card :heading="__('Hrm module is alive')" :subheading="__('This page comes from app/Modules/Hrm/resources/views/ping.blade.php')">
        <x-slot:actions>
            <flux:badge>{{ __('Demo') }}</flux:badge>
        </x-slot:actions>

        {{-- A Livewire component that lives inside the Hrm module --}}
        <livewire:hrm::ping />

        <x-slot:footer>{{ __('Buttons above: Livewire counter, toast and confirm modal.') }}</x-slot:footer>
    </x-ui.card>
</x-layouts.app>
