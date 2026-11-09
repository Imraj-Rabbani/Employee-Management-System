{{--
    HRM → Job Postings (6.3). The list + the New / Edit modal. [REC-02]
--}}
<x-layouts.app :title="__('Job postings')">
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <flux:subheading>{{ __('Advertise approved manpower requests. Applicants are added to an open posting.') }}</flux:subheading>
            <div class="flex gap-2">
                @can('hrm.recruitment.setup')
                    <flux:button icon="cog-6-tooth" :href="route('hrm.recruitment.setup.factors')" wire:navigate>{{ __('Setup') }}</flux:button>
                @endcan
                <flux:button variant="primary" icon="plus" x-on:click="$dispatch('create-job-posting')">{{ __('New posting') }}</flux:button>
            </div>
        </div>

        <livewire:hrm::recruitment.job-posting-table />
        <livewire:hrm::recruitment.job-posting-form :for="request()->integer('for') ?: null" />
    </div>
</x-layouts.app>
