<div>
    <x-ui.modal :name="$modal" size="lg" :heading="$record ? __('Edit entity') : __('New entity')">
        <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Type') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    {{-- .live: the parent list depends on the type (only other types' entities). --}}
                    <flux:select aria-required="true" wire:model.live="form.entity_type_id">
                        <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                        @foreach ($types as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.entity_type_id" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Code') }}</flux:label>
                    <flux:input wire:model="form.code" />
                    <flux:error name="form.code" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:description>{{ __('e.g. Finance, Gulshan, Dhaka') }}</flux:description>
                <flux:input aria-required="true" wire:model="form.name" />
                <flux:error name="form.name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Head') }}</flux:label>
                <flux:description>{{ __('Approves when a workflow step is "Head of entity".') }}</flux:description>
                <flux:select wire:model="form.head_employee_id">
                    <flux:select.option value="">{{ __('Nobody') }}</flux:select.option>
                    @foreach ($people as $optionValue => $optionLabel)
                        <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.head_employee_id" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Parent') }}</flux:label>
                <flux:description>{{ __('Optional, e.g. Branch Gulshan under Division Dhaka. When this entity has no head, approvals go to the parent\'s head.') }}</flux:description>
                <flux:select wire:model="form.parent_id">
                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                    @foreach ($parents as $optionValue => $optionLabel)
                        <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.parent_id" />
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
