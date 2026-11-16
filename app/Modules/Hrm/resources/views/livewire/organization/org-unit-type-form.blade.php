<div>
    <x-ui.modal :name="$modal" :heading="$record ? __('Edit unit level') : __('New unit level')">
        <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-[2fr_1fr]">
                <flux:field>
                    <flux:label>{{ __('Name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('e.g. Department') }}</flux:description>
                    <flux:input aria-required="true" wire:model="form.name" />
                    <flux:error name="form.name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Order') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" wire:model="form.sequence" type="number" min="0" />
                    <flux:error name="form.sequence" />
                </flux:field>
            </div>
            <flux:field>
                <flux:label>{{ __('Sits under') }}</flux:label>
                <flux:description>{{ __('Units of this level must be placed under a unit of that level.') }}</flux:description>
                <flux:select wire:model="form.parent_type_id">
                    <flux:select.option value="">{{ __('Nothing (top level)') }}</flux:select.option>
                    @foreach ($parentTypes as $optionValue => $optionLabel)
                        <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.parent_type_id" />
            </flux:field>

            <flux:switch wire:model="form.is_active" :label="__('Active')" />

            {{-- Delete: only when editing an existing record --}}
            @if ($record)
                <flux:separator />
                <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600" x-on:click="$dispatch('confirm', {
                    title: @js(__('Delete this record?')),
                    message: @js(__('Only possible when nothing uses it any more.')),
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
