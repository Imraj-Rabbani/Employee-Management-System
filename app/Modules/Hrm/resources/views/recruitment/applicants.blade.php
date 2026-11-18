{{--
    HRM → Applicants: everyone HR has entered.
--}}
<x-layouts.app :title="__('Recruitment')">
    <div class="space-y-4">
        <x-ui.section-tabs section="hrm.recruitment" />

        <div class="flex flex-wrap items-center justify-between gap-4">
            <flux:subheading>{{ __('People who applied. One record per person, however many postings they apply to.') }}</flux:subheading>
        </div>

        <livewire:hrm::recruitment.applicant-table />
        <livewire:hrm::recruitment.applicant-form />
    </div>
</x-layouts.app>
