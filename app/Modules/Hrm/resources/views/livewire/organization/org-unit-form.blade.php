<div>
    <x-ui.modal :name="$modal" size="lg" :heading="$record ? __('Edit org unit') : __('New org unit')">
        <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-[2fr_1fr]">
                <flux:field>
                    <flux:label>{{ __('Name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('e.g. Accounts') }}</flux:description>
                    <flux:input aria-required="true" wire:model="form.name" />
                    <flux:error name="form.name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Code') }}</flux:label>
                    <flux:input wire:model="form.code" />
                    <flux:error name="form.code" />
                </flux:field>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Level') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:select aria-required="true" wire:model="form.org_unit_type_id">
                        <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                        @foreach ($types as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.org_unit_type_id" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Under') }}</flux:label>
                    <flux:select wire:model="form.parent_id">
                        <flux:select.option value="">{{ __('Nothing (top level)') }}</flux:select.option>
                        @foreach ($parents as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.parent_id" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Cost center') }}</flux:label>
                <flux:select wire:model="form.cost_center_id">
                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                    @foreach ($costCenters as $optionValue => $optionLabel)
                        <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.cost_center_id" />
            </flux:field>

            <flux:switch wire:model="form.is_active" :label="__('Active')" />

            @if ($record)
                <flux:text class="text-xs">{{ __('The unit head cannot be chosen here yet.') }}</flux:text>
            @endif

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
