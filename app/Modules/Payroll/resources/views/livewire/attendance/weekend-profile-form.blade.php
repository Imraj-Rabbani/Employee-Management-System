<div>
    <x-ui.modal :name="$modal" :heading="$record ? __('Edit weekend profile') : __('New weekend profile')">
        <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-[1fr_2fr]">
                <flux:field>
                    <flux:label>{{ __('Code') }}</flux:label>
                    <flux:input wire:model="form.code" />
                    <flux:error name="form.code" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('e.g. Friday & Saturday') }}</flux:description>
                    <flux:input aria-required="true" wire:model="form.name" />
                    <flux:error name="form.name" />
                </flux:field>
            </div>

            <flux:separator :text="__('Days off')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('First weekend day') }}</flux:label>
                    <flux:select wire:model="form.day_one">
                        <flux:select.option value="">{{ __('None') }}</flux:select.option>
                        @foreach ($days as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.day_one" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Weeks') }}</flux:label>
                    <flux:select wire:model="form.alternate_one">
                        <flux:select.option value="">{{ __('Every week') }}</flux:select.option>
                        @foreach ($parities as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.alternate_one" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Second weekend day') }}</flux:label>
                    <flux:select wire:model="form.day_two">
                        <flux:select.option value="">{{ __('None') }}</flux:select.option>
                        @foreach ($days as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.day_two" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Weeks') }}</flux:label>
                    <flux:select wire:model="form.alternate_two">
                        <flux:select.option value="">{{ __('Every week') }}</flux:select.option>
                        @foreach ($parities as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.alternate_two" />
                </flux:field>
            </div>
            <flux:text class="text-sm">{{ __('Odd / even counts that weekday from 1 January: the 1st, 3rd, 5th … Saturday of the year are odd.') }}</flux:text>

            <flux:field>
                <flux:label>{{ __('Home office day') }}</flux:label>
                <flux:select wire:model="form.home_office_day">
                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                    @foreach ($days as $optionValue => $optionLabel)
                        <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.home_office_day" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Detail') }}</flux:label>
                <flux:textarea wire:model="form.detail" rows="2" />
                <flux:error name="form.detail" />
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
