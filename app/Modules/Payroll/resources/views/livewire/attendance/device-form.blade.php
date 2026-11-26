<div>
    <x-ui.modal :name="$modal" :heading="$record ? __('Edit device') : __('New device')">
        <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input :placeholder="__('e.g. Main gate')" aria-required="true" wire:model="form.name" />
                    <flux:error name="form.name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Serial number') }}</flux:label>
                    <flux:input wire:model="form.serial_number" />
                    <flux:error name="form.serial_number" />
                </flux:field>
            </div>
            <div class="grid gap-4 sm:grid-cols-[2fr_1fr]">
                <flux:field>
                    <flux:label>{{ __('IP address') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('Reachable from the server.') }}</flux:description>
                    <flux:input aria-required="true" wire:model="form.ip" />
                    <flux:error name="form.ip" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Port') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" type="number" wire:model="form.port" />
                    <flux:error name="form.port" />
                </flux:field>
            </div>
            <flux:field>
                <flux:label>{{ __('Entity') }}</flux:label>
                <flux:select wire:model="form.entity_id">
                    <flux:select.option value="">{{ __('Not tied to an entity') }}</flux:select.option>
                    @foreach ($units as $optionValue => $optionLabel)
                        <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.entity_id" />
            </flux:field>

            <flux:switch wire:model="form.is_active" :label="__('Active')" :description="__('Only active devices are synced.')" />
            <flux:text class="text-sm">{{ __('The device must not have a communication password (comm key): the sync cannot send one.') }}</flux:text>

            @if ($record)
                <flux:separator />
                <div class="flex flex-wrap items-center gap-3">
                    <flux:button size="sm" icon="arrow-path" wire:click="syncNow" wire:loading.attr="disabled" wire:target="syncNow">{{ __('Sync now') }}</flux:button>
                    <flux:text class="text-sm" wire:loading wire:target="syncNow">{{ __('Talking to the device… (up to 15 seconds)') }}</flux:text>
                    @if ($record->last_sync_error)
                        <flux:text class="text-sm text-red-700 dark:text-red-300">{{ $record->last_sync_error }}</flux:text>
                    @elseif ($record->last_synced_at)
                        <flux:text class="text-sm">{{ __('Last synced :time', ['time' => format_datetime($record->last_synced_at)]) }}</flux:text>
                    @endif
                </div>
            @endif

            {{-- Delete: only when editing an existing record --}}
            @if ($record)
                <flux:separator />
                <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600" x-on:click="$dispatch('confirm', {
                    title: {{ Js::from(__('Delete this record?')) }},
                    message: {{ Js::from(__('Its punches are kept. Make it inactive instead if it is only switched off for a while.')) }},
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
