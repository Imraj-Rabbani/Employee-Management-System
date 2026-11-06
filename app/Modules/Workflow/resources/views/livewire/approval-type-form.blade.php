<div>
    <x-ui.modal name="approval-type-form" :heading="$typeId ? __('Edit approval type') : __('New approval type')">
        <form wire:submit="save" id="approval-type-form" class="space-y-6">
            @if ($typeId)
                <x-form.input :value="$key" :label="__('Key')" disabled :hint="__('Used by the program. Cannot be changed.')" />
            @else
                <x-form.input wire:model="key" :label="__('Key')" required :hint="__('Used by the program, e.g. leave. Cannot be changed later.')" />
            @endif

            <x-form.input wire:model="name" :label="__('Name')" required :hint="__('Shown on screen, e.g. Leave application')" />

            <x-form.toggle wire:model="isChained" :label="__('Levels one after another')"
                :hint="__('On: level 2 is asked after level 1 approves. Off: all levels are asked at once.')" />

            <x-form.toggle wire:model="isActive" :label="__('Active')" :hint="__('Inactive: documents of this type cannot be submitted.')" />
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="approval-type-form" variant="primary">{{ __('Save') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
