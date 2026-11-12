{{--
    HRM → Leave → Encashments (8.6). Unused leave paid out: approved days leave the balance at once,
    the money comes with the next payroll run (Phase 10). [LV-06]
--}}
<x-layouts.app :title="__('Leave')">
    <div class="space-y-4">
        <x-ui.section-tabs section="hrm.leave" />

        <div class="flex flex-wrap items-center justify-between gap-4">
            <flux:subheading>{{ __('Approved days leave the balance at once; the amount is worked out and paid by the next payroll run.') }}</flux:subheading>
            <flux:button variant="primary" icon="plus" x-on:click="$dispatch('create-encashment')">{{ __('Encash leave') }}</flux:button>
        </div>

        <livewire:hrm::leave.encashment-table />
        <livewire:hrm::leave.encashment-form />
    </div>
</x-layouts.app>
