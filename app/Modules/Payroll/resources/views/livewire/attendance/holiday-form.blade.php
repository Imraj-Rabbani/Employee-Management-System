<div>
    <x-ui.modal :name="$modal" :heading="$record ? __('Edit holiday') : __('New holiday')">
        <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-[1fr_2fr]">
                <flux:field>
                    <flux:label>{{ __('Date') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input type="date" aria-required="true" wire:model="form.date" />
                    <flux:error name="form.date" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Title') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('e.g. Victory Day') }}</flux:description>
                    <flux:input aria-required="true" wire:model="form.title" />
                    <flux:error name="form.title" />
                </flux:field>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:switch wire:model="form.is_public" :label="__('Public')" />

                <flux:switch wire:model="form.is_festival" :label="__('Festival')" />
                <flux:switch wire:model="form.repeats_yearly" :label="__('Every year')" :description="__('Same day and month')" />
            </div>

            <flux:field>
                <flux:label>{{ __('In holiday calendars') }}</flux:label>
                <flux:description>{{ $profiles->isEmpty() ? __('There are no holiday calendars yet: add one on the Holiday calendars tab.') : __('Only employees on these calendars get the day off.') }}</flux:description>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($profiles as $profile)
                        <flux:checkbox wire:model="profileIds" value="{{ $profile->id }}"
                            :label="$profile->name.($profile->is_active ? '' : ' ('.__('inactive').')')" />
                    @endforeach
                </div>
                <flux:error name="profileIds" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Detail') }}</flux:label>
                <flux:textarea wire:model="form.detail" rows="2" />
                <flux:error name="form.detail" />
            </flux:field>

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
