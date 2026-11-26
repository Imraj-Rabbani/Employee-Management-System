<div>
    <x-ui.modal :name="$modal" :heading="$record ? __('Edit interview question') : __('New interview question')"
        :subheading="__('Interviewers score each question from 1 to 5. Group questions into a question set to use them.')">
        <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
            <flux:field>
                <flux:label>{{ __('Question') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:textarea aria-required="true" rows="2" wire:model="form.name" :placeholder="__('e.g. Explains object-oriented programming clearly')" />
                <flux:error name="form.name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Hint for interviewers') }}</flux:label>
                <flux:textarea rows="3" wire:model="form.description" :placeholder="__('e.g. What a 5 looks like')" />
                <flux:error name="form.description" />
            </flux:field>

            <div class="grid items-start gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Order') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" wire:model="form.sequence" type="number" min="0" />
                    <flux:description>{{ __('Order in this list') }}</flux:description>
                    <flux:error name="form.sequence" />
                </flux:field>

                <flux:switch wire:model="form.is_active" :label="__('Active')" :description="__('Inactive questions cannot be added to sets')" />
            </div>

            {{-- Delete: only when editing an existing record --}}
            @if ($record)
                <flux:separator />
                <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600" x-on:click="$dispatch('confirm', {
                    title: {{ Js::from(__('Delete this question?')) }},
                    message: {{ Js::from(__('Only possible when no question set uses it.')) }},
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
