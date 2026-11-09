{{--
    HRM → Recruitment → one manpower request (6.2). The route passes $request (already checked with visibleTo).
--}}
<x-layouts.app :title="$request->number()">
    <div class="space-y-4">
        <flux:link :href="route('hrm.recruitment.requests')" wire:navigate class="inline-flex items-center gap-1 text-sm">
            <flux:icon.arrow-left variant="micro" /> {{ __('All manpower requests') }}
        </flux:link>

        <livewire:hrm::recruitment.manpower-request-details :request-id="$request->id" />
        <livewire:hrm::recruitment.manpower-request-form />
    </div>
</x-layouts.app>
