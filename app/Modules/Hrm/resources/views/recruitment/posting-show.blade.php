{{--
    HRM → Job Postings → one posting (6.3). The route passes $posting (already checked with visibleTo).
--}}
<x-layouts.app :title="$posting->number()">
    <div class="space-y-4">
        <x-ui.section-tabs section="hrm.recruitment" />

        <flux:link :href="route('hrm.recruitment.postings')" wire:navigate class="inline-flex items-center gap-1 text-sm">
            <flux:icon.arrow-left variant="micro" /> {{ __('All job postings') }}
        </flux:link>

        <livewire:hrm::recruitment.job-posting-details :posting-id="$posting->id" />
        {{-- Separate component: it redraws on its own (tabs, moves) without redrawing the details. --}}
        <livewire:hrm::recruitment.application-pipeline :posting-id="$posting->id" />
        {{-- Interviews of this posting (6.6): plan, complete, scores, and the ranking. --}}
        <livewire:hrm::recruitment.posting-interviews :posting-id="$posting->id" />
        <livewire:hrm::recruitment.job-posting-form />
        <livewire:hrm::recruitment.applicant-form />
        <livewire:hrm::recruitment.application-history />
        <livewire:hrm::recruitment.interview-form />
        <livewire:hrm::recruitment.interview-score-sheet />
        <livewire:hrm::recruitment.offer-form />
    </div>
</x-layouts.app>
