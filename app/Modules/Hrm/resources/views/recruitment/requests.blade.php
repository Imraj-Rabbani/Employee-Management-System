{{--
    HRM → Recruitment → Manpower requests (6.2). The list + the New / Edit modal. [REC-01]
--}}
<x-layouts.app :title="__('Recruitment')">
    <div class="space-y-4">
        <x-ui.section-tabs section="hrm.recruitment" />

        <div class="flex flex-wrap items-center justify-between gap-4">
            <flux:subheading>{{ __('Ask for people. Once a request is approved, HR can advertise it.') }}</flux:subheading>
            <div class="flex gap-2">
                <flux:button variant="primary" icon="plus" x-on:click="$dispatch('create-manpower-request')">{{ __('New request') }}</flux:button>
            </div>
        </div>

        <livewire:hrm::recruitment.manpower-request-table />
        <livewire:hrm::recruitment.manpower-request-form />
    </div>
</x-layouts.app>
