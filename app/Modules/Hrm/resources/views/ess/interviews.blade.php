{{-- Self Service → My Interviews (6.6). [REC-05] --}}
<x-layouts.app :title="__('My Interviews')">
    <div class="space-y-4">
        <flux:subheading>{{ __('Interviews you are on the panel of. Score each applicant after the interview and send your sheet.') }}</flux:subheading>
        <livewire:hrm::ess.my-interviews />
        <livewire:hrm::recruitment.interview-score-sheet />
    </div>
</x-layouts.app>
