<div>
    <x-ui.modal :name="$modal" :heading="$record ? __('Edit grade') : __('New grade')">
        <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('Code') }}</flux:label>
                    <flux:input wire:model="form.code" />
                    <flux:error name="form.code" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" wire:model="form.name" />
                    <flux:error name="form.name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Order') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('Lists are sorted by this') }}</flux:description>
                    <flux:input aria-required="true" wire:model="form.sequence" type="number" min="0" />
                    <flux:error name="form.sequence" />
                </flux:field>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Salary from') }}</flux:label>
                    <flux:description>{{ __('Monthly gross. Offers are compared with this band.') }}</flux:description>
                    <flux:input wire:model="form.salary_min" type="number" min="0" step="0.01" />
                    <flux:error name="form.salary_min" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Salary to') }}</flux:label>
                    <flux:input wire:model="form.salary_max" type="number" min="0" step="0.01" />
                    <flux:error name="form.salary_max" />
                </flux:field>
            </div>
            <flux:field>
                <flux:label>{{ __('Description') }}</flux:label>
                <flux:textarea rows="3" wire:model="form.description" />
                <flux:error name="form.description" />
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
