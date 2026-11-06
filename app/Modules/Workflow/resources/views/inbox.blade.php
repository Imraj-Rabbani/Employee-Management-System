<x-layouts.app :title="__('My Approvals')">
    <div class="space-y-4">
        <flux:subheading>{{ __('Requests waiting for your answer. Click a subject to open it.') }}</flux:subheading>

        <livewire:workflow::my-approvals-table />
        <livewire:workflow::respond-form />
    </div>
</x-layouts.app>
