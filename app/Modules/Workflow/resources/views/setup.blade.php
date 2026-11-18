<x-layouts.app :title="__('Workflow Setup')">
    {{-- Tabs: Alpine keeps the open tab in "tab"; each pane below shows with x-show. --}}
    <div x-data="{ tab: 'workflows' }">
        <div class="mb-4 flex gap-1 border-b border-zinc-200 dark:border-white/10" role="tablist">
            <button type="button" role="tab" x-on:click="tab = 'workflows'" x-bind:aria-selected="tab === 'workflows'"
                x-bind:class="tab === 'workflows' ? 'border-brand-800 text-brand-900 dark:border-white dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'" class="-mb-px border-b-2 px-3 py-2 text-sm font-medium transition">{{ __('Workflows') }}</button>
            <button type="button" role="tab" x-on:click="tab = 'types'" x-bind:aria-selected="tab === 'types'"
                x-bind:class="tab === 'types' ? 'border-brand-800 text-brand-900 dark:border-white dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'" class="-mb-px border-b-2 px-3 py-2 text-sm font-medium transition">{{ __('Approval types') }}</button>
        </div>

        <div x-show="tab === 'workflows'" class="space-y-4">
            <div class="flex items-center justify-between gap-4">
                <flux:subheading>{{ __('Who approves what. The most specific active workflow of a type is used.') }}</flux:subheading>
            </div>

            <livewire:workflow::workflows-table />
        </div>

        <div x-show="tab === 'types'" x-cloak class="space-y-4">
            <div class="flex items-center justify-between gap-4">
                <flux:subheading>{{ __('Kinds of documents that need approval, e.g. leave or loans.') }}</flux:subheading>
            </div>

            <livewire:workflow::approval-types-table />
        </div>
    </div>

    <livewire:workflow::workflow-form />
    <livewire:workflow::approval-type-form />
</x-layouts.app>
