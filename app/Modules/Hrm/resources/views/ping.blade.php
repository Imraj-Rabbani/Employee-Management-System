<x-layouts.app :title="__('Hrm ping')">
    <flux:heading size="xl">Hrm module is alive</flux:heading>
    <flux:text class="mt-2">This page comes from app/Modules/Hrm/resources/views/ping.blade.php</flux:text>

    {{-- A Livewire component that lives inside the Hrm module --}}
    <livewire:hrm::ping />
</x-layouts.app>
