{{--
    HRM → Recruitment → Manpower requests (6.2). The list + the New / Edit modal. [REC-01]
--}}
<x-layouts.app :title="__('Manpower requests')">
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <flux:subheading>{{ __('Ask for people. Once a request is approved, HR can advertise it.') }}</flux:subheading>
            <div class="flex gap-2">
                @can('hrm.recruitment.setup')
                    <flux:button icon="cog-6-tooth" :href="route('hrm.recruitment.setup.factors')" wire:navigate>{{ __('Setup') }}</flux:button>
                @endcan
                <flux:button variant="primary" icon="plus" x-on:click="$dispatch('create-manpower-request')">{{ __('New request') }}</flux:button>
            </div>
        </div>

        <livewire:hrm::recruitment.manpower-request-table />
        <livewire:hrm::recruitment.manpower-request-form />
    </div>
</x-layouts.app>
