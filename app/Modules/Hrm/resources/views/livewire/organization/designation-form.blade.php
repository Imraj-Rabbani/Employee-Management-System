<x-form.lookup-modal :$modal :$record :heading="$record ? __('Edit designation') : __('New designation')">
        <div class="grid gap-4 sm:grid-cols-[1fr_2fr]">
            <x-form.input wire:model="form.code" :label="__('Code')" />
            <x-form.input wire:model="form.name" :label="__('Name')" required :hint="__('e.g. Senior Officer')" />
        </div>
        <x-form.toggle wire:model="form.is_active" :label="__('Active')" />
</x-form.lookup-modal>
