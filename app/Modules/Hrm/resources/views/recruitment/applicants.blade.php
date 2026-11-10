{{--
    HRM → Applicants (6.4): everyone HR has entered. [REC-03]
--}}
<x-layouts.app :title="__('Recruitment')">
    <div class="space-y-4">
        <x-ui.section-tabs section="hrm.recruitment" />

        <div class="flex flex-wrap items-center justify-between gap-4">
            <flux:subheading>{{ __('People who applied. One record per person, however many postings they apply to.') }}</flux:subheading>
            <flux:button variant="primary" icon="plus" x-on:click="$dispatch('create-applicant')">{{ __('New applicant') }}</flux:button>
        </div>

        <livewire:hrm::recruitment.applicant-table />
        <livewire:hrm::recruitment.applicant-form />
    </div>
</x-layouts.app>
