{{--
    Modal for a LookupForm (App\Core\Livewire\LookupForm): heading, the fields (slot), Save, and Delete when editing.

    <x-form.lookup-modal :$modal :$record :heading="$record ? __('Edit branch') : __('New branch')">
        <x-form.input wire:model="form.name" :label="__('Name')" required />
    </x-form.lookup-modal>
--}}
@props([
    'modal',
    'record' => null,
    'heading' => '',
    'size' => 'md',
])

<div>
    <x-ui.modal :name="$modal" :size="$size" :heading="$heading">
        <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
            {{ $slot }}

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
