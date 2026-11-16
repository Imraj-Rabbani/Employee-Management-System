<div>
    <x-ui.modal name="fiscal-year-form" :heading="$yearId ? __('Edit fiscal year') : __('New fiscal year')">
        {{-- Tabs: Alpine keeps the open tab in "tab"; each pane below shows with x-show. History only when editing. --}}
        <div x-data="{ tab: 'details' }" wire:key="year-tabs-{{ $yearId ?? 'new' }}">
            <div class="mb-4 flex gap-1 border-b border-zinc-200 dark:border-white/10" role="tablist">
                <button type="button" role="tab" x-on:click="tab = 'details'" x-bind:aria-selected="tab === 'details'"
                    x-bind:class="tab === 'details' ? 'border-brand-800 text-brand-900 dark:border-white dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'"
                    class="-mb-px border-b-2 px-3 py-2 text-sm font-medium transition">{{ __('Details') }}</button>

                @if ($yearId)
                    <button type="button" role="tab" x-on:click="tab = 'history'" x-bind:aria-selected="tab === 'history'"
                        x-bind:class="tab === 'history' ? 'border-brand-800 text-brand-900 dark:border-white dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'"
                        class="-mb-px border-b-2 px-3 py-2 text-sm font-medium transition">{{ __('History') }}</button>
                @endif
            </div>

            <div x-show="tab === 'details'">
                <form wire:submit="save" id="fiscal-year-form" class="space-y-6">
                    <flux:field>
                        <flux:label>{{ __('Name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                        <flux:description>{{ __('e.g. FY 2025-26') }}</flux:description>
                        <flux:input aria-required="true" wire:model="name" />
                        <flux:error name="name" />
                    </flux:field>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('Starts') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                            <flux:input type="date" aria-required="true" wire:model="startDate" />
                            <flux:error name="startDate" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Ends') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                            <flux:input type="date" aria-required="true" wire:model="endDate" />
                            <flux:error name="endDate" />
                        </flux:field>
                    </div>
                </form>
            </div>

            @if ($yearId)
                <div x-show="tab === 'history'" x-cloak>
                    <livewire:core::history :subject="$year" can="core.fiscal_years.manage" :key="'history-year-'.$yearId" />
                </div>
            @endif
        </div>

        <x-slot:footer>
            <flux:button type="submit" form="fiscal-year-form" variant="primary">{{ __('Save') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
