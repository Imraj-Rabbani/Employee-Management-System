<x-layouts.app :title="__('Fiscal Years')">
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-4">
            <flux:subheading>{{ __('One year is active at a time. It drives the tax year and reports.') }}</flux:subheading>

            <flux:button variant="primary" icon="plus" x-on:click="$dispatch('create-fiscal-year')">{{ __('New fiscal year') }}</flux:button>
        </div>

        <livewire:core::fiscal-years-table />
        <livewire:core::fiscal-year-form />
    </div>
</x-layouts.app>
