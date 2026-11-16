{{-- HRM → Employees → Import. --}}
<x-layouts.app :title="__('Import employees')">
    <div class="space-y-4">
        <flux:link :href="route('hrm.employees.index')" wire:navigate class="inline-flex items-center gap-1 text-sm">
            <flux:icon.arrow-left variant="micro" /> {{ __('All employees') }}
        </flux:link>

        <livewire:hrm::employees.employee-imports />
    </div>
</x-layouts.app>
