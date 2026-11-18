{{--
    HRM → Job Postings. The list + the New / Edit modal.
--}}
<x-layouts.app :title="__('Recruitment')">
    <div class="space-y-4">
        <x-ui.section-tabs section="hrm.recruitment" />

        <div class="flex flex-wrap items-center justify-between gap-4">
            <flux:subheading>{{ __('Advertise approved manpower requests. Applicants are added to an open posting.') }}</flux:subheading>
        </div>

        <livewire:hrm::recruitment.job-posting-table />
        <livewire:hrm::recruitment.job-posting-form :for="request()->integer('for') ?: null" />
    </div>
</x-layouts.app>
