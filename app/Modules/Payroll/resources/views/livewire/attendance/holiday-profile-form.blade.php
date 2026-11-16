<div>
    <x-ui.modal :name="$modal" :heading="$record ? __('Edit holiday calendar') : __('New holiday calendar')">
        <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
            <flux:field>
                <flux:label>{{ __('Name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:description>{{ __('e.g. Head office, Factory') }}</flux:description>
                <flux:input aria-required="true" wire:model="form.name" />
                <flux:error name="form.name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Detail') }}</flux:label>
                <flux:textarea wire:model="form.detail" rows="2" />
                <flux:error name="form.detail" />
            </flux:field>

            <flux:switch wire:model="form.is_active" :label="__('Active')" />
            @if ($record)
                <flux:text class="text-sm">{{ __('Choose its holidays on the Holidays tab (open a holiday, tick this calendar).') }}</flux:text>
            @endif

            {{-- Delete: only when editing an existing record --}}
            @if ($record)
                <flux:separator />
                <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600" x-on:click="$dispatch('confirm', {
                    title: @js(__('Delete this record?')),
                    message: @js(__('Its holidays stay; only the calendar is removed. Not possible while employees use it.')),
                    confirmText: @js(__('Delete')),
                    onConfirm: () => $wire.delete(),
                })">{{ __('Delete') }}</flux:button>
            @endif
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="{{ $modal }}" variant="primary">{{ __('Save') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
