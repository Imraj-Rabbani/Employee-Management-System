<div>
    <x-ui.modal :name="$modal" :heading="$record ? __('Edit employment type') : __('New employment type')">
        <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
            <flux:field>
                <flux:label>{{ __('Name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:input :placeholder="__('e.g. Permanent, Contract')" aria-required="true" wire:model="form.name" />
                <flux:error name="form.name" />
            </flux:field>

            <flux:switch wire:model="form.is_active" :label="__('Active')" />

            {{-- Delete: only when editing an existing record --}}
            @if ($record)
                <flux:separator />
                <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600" x-on:click="$dispatch('confirm', {
                    title: {{ Js::from(__('Delete this record?')) }},
                    message: {{ Js::from(__('Only possible when nothing uses it any more.')) }},
                    confirmText: {{ Js::from(__('Delete')) }},
                    onConfirm: () => $wire.delete(),
                })">{{ __('Delete') }}</flux:button>
            @endif
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="{{ $modal }}" variant="primary">{{ __('Save') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
