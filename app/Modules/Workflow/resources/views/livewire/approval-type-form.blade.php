<div>
    <x-ui.modal name="approval-type-form" :heading="$typeId ? __('Edit approval type') : __('New approval type')">
        <form wire:submit="save" id="approval-type-form" class="space-y-6">
            @if ($typeId)
                <flux:field>
                    <flux:label>{{ __('Key') }}</flux:label>
                    <flux:description>{{ __('Used by the program. Cannot be changed.') }}</flux:description>
                    <flux:input :value="$key" disabled />
                </flux:field>
            @else
                <flux:field>
                    <flux:label>{{ __('Key') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('Used by the program, e.g. leave. Cannot be changed later.') }}</flux:description>
                    <flux:input aria-required="true" wire:model="key" />
                    <flux:error name="key" />
                </flux:field>
            @endif

            <flux:field>
                <flux:label>{{ __('Name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:description>{{ __('Shown on screen, e.g. Leave application') }}</flux:description>
                <flux:input aria-required="true" wire:model="name" />
                <flux:error name="name" />
            </flux:field>

            <flux:switch wire:model="isChained" :label="__('Levels one after another')" :description="__('On: level 2 is asked after level 1 approves. Off: all levels are asked at once.')" />

            <flux:switch wire:model="isActive" :label="__('Active')" :description="__('Inactive: documents of this type cannot be submitted.')" />
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="approval-type-form" variant="primary">{{ __('Save') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
