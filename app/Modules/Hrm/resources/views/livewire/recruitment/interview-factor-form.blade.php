<div>
    <x-ui.modal :name="$modal" :heading="$record ? __('Edit interview factor') : __('New interview factor')">
        <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
            <flux:field>
                <flux:label>{{ __('Name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:input aria-required="true" wire:model="form.name" />
                <flux:error name="form.name" />
            </flux:field>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Weight (%)') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('Other active factors: :used%', ['used' => $otherWeight]) }}</flux:description>
                    <flux:input aria-required="true" wire:model="form.weight" type="number" min="0" max="100" step="0.01" />
                    <flux:error name="form.weight" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Order') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('Order on the score sheet') }}</flux:description>
                    <flux:input aria-required="true" wire:model="form.sequence" type="number" min="0" />
                    <flux:error name="form.sequence" />
                </flux:field>
            </div>
            <flux:field>
                <flux:label>{{ __('Description') }}</flux:label>
                <flux:description>{{ __('A hint for interviewers: what to look for') }}</flux:description>
                <flux:textarea rows="3" wire:model="form.description" />
                <flux:error name="form.description" />
            </flux:field>

            <flux:switch wire:model="form.is_required" :label="__('Required')" :description="__('Interviewers must score this factor')" />
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
