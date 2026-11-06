<x-layouts.app :title="__('Workflow Setup')">
    <x-ui.tabs :tabs="['workflows' => __('Workflows'), 'types' => __('Approval types')]">
        <div x-show="tab === 'workflows'" class="space-y-4">
            <div class="flex items-center justify-between gap-4">
                <flux:subheading>{{ __('Who approves what. The most specific active workflow of a type is used.') }}</flux:subheading>
                <flux:button variant="primary" icon="plus" x-on:click="$dispatch('create-workflow')">{{ __('New workflow') }}</flux:button>
            </div>

            <livewire:workflow::workflows-table />
        </div>

        <div x-show="tab === 'types'" x-cloak class="space-y-4">
            <div class="flex items-center justify-between gap-4">
                <flux:subheading>{{ __('Kinds of documents that need approval, e.g. leave or loans.') }}</flux:subheading>
                <flux:button variant="primary" icon="plus" x-on:click="$dispatch('create-approval-type')">{{ __('New approval type') }}</flux:button>
            </div>

            <livewire:workflow::approval-types-table />
        </div>
    </x-ui.tabs>

    <livewire:workflow::workflow-form />
    <livewire:workflow::approval-type-form />
</x-layouts.app>
