<div>
    <x-ui.modal :name="$modal" :heading="$record ? __('Edit cost center') : __('New cost center')">
        <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-[1fr_2fr]">
                <flux:field>
                    <flux:label>{{ __('Code') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" wire:model="form.code" />
                    <flux:error name="form.code" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" wire:model="form.name" />
                    <flux:error name="form.name" />
                </flux:field>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Function code') }}</flux:label>
                    <flux:input wire:model="form.function_code" />
                    <flux:error name="form.function_code" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('GL account') }}</flux:label>
                    <flux:description>{{ __('Used by the accounting export') }}</flux:description>
                    <flux:input wire:model="form.account_code" />
                    <flux:error name="form.account_code" />
                </flux:field>
            </div>
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
