<div>
    <x-ui.modal name="fiscal-year-form" :heading="$yearId ? __('Edit fiscal year') : __('New fiscal year')">
        <x-ui.tabs :tabs="$yearId ? ['details' => __('Details'), 'history' => __('History')] : ['details' => __('Details')]" wire:key="year-tabs-{{ $yearId ?? 'new' }}">
            <div x-show="tab === 'details'">
                <form wire:submit="save" id="fiscal-year-form" class="space-y-6">
                    <x-form.input wire:model="name" :label="__('Name')" :hint="__('e.g. FY 2025-26')" required />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-form.date wire:model="startDate" :label="__('Starts')" required />
                        <x-form.date wire:model="endDate" :label="__('Ends')" required />
                    </div>
                </form>
            </div>

            @if ($yearId)
                <div x-show="tab === 'history'" x-cloak>
                    <livewire:core::history :subject="$year" can="core.fiscal_years.manage" :key="'history-year-'.$yearId" />
                </div>
            @endif
        </x-ui.tabs>

        <x-slot:footer>
            <flux:button type="submit" form="fiscal-year-form" variant="primary">{{ __('Save') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
