<x-layouts.app :title="__('Workflow Setup')">
    <div class="space-y-4">
        <flux:subheading>{{ __('Every request that needs approval is listed here. Open one to set who approves it, level by level: level 1 is asked first, and once it approves the request moves on to level 2, and so on.') }}</flux:subheading>

        <livewire:workflow::workflows-table />
    </div>

    <livewire:workflow::workflow-form />
</x-layouts.app>
