{{--
    HRM → Job Postings → an offer (6.7). The route passes $offer (already checked through visibleTo). [REC-06]
--}}
<x-layouts.app :title="$offer->number()">
    <div class="space-y-4">
        <flux:link :href="route('hrm.recruitment.postings.show', $offer->application->job_posting_id)" wire:navigate class="inline-flex items-center gap-1 text-sm">
            <flux:icon.arrow-left variant="micro" /> {{ __('Back to the job posting') }}
        </flux:link>

        <livewire:hrm::recruitment.offer-details :offer-id="$offer->id" />
        <livewire:hrm::recruitment.offer-form />
    </div>
</x-layouts.app>
