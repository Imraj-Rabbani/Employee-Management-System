<div>
    <x-ui.modal name="employee-photo-form" :heading="__('Photo')" :subheading="__('JPG, PNG or WebP, max 2 MB. A square photo looks best.')">
        <form wire:submit="save" id="employee-photo-form" class="space-y-5">
            <div class="flex justify-center">
                {{-- Preview: the newly chosen file, else the current photo, else initials. --}}
                @if ($photo?->isPreviewable() && ! $errors->has('photo'))
                    <img src="{{ $photo->temporaryUrl() }}" alt="" class="size-32 rounded-full object-cover">
                @else
                    <flux:avatar size="xl" class="!size-32" :name="$employee?->name" :src="$employee?->photo_url" />
                @endif
            </div>

            <x-form.file wire:model="photo" :label="__('Choose a photo')" accept="image/jpeg,image/png,image/webp" />

            @if ($employee?->photo_path)
                <flux:separator />
                <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600" x-on:click="$dispatch('confirm', {
                    title: @js(__('Remove the photo?')),
                    confirmText: @js(__('Remove')),
                    onConfirm: () => $wire.remove(),
                })">{{ __('Remove photo') }}</flux:button>
            @endif
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="employee-photo-form" variant="primary">{{ __('Save') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
