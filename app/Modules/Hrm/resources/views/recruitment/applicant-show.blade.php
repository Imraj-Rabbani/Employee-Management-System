{{--
    HRM → Applicants → one applicant (6.4). The route passes $applicant. [REC-03]
--}}
<x-layouts.app :title="$applicant->name">
    <div class="space-y-4">
        <flux:link :href="route('hrm.recruitment.applicants')" wire:navigate class="inline-flex items-center gap-1 text-sm">
            <flux:icon.arrow-left variant="micro" /> {{ __('All applicants') }}
        </flux:link>

        <livewire:hrm::recruitment.applicant-profile :applicant-id="$applicant->id" />

        <div class="grid gap-4 lg:grid-cols-2">
            <livewire:hrm::recruitment.applicant-educations :applicant-id="$applicant->id" />
            <livewire:hrm::recruitment.applicant-work-histories :applicant-id="$applicant->id" />
        </div>

        <livewire:hrm::recruitment.applicant-form />
    </div>
</x-layouts.app>
