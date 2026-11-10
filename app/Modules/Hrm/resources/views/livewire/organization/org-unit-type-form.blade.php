<x-form.lookup-modal :$modal :$record :heading="$record ? __('Edit unit level') : __('New unit level')">
        <div class="grid gap-4 sm:grid-cols-[2fr_1fr]">
            <x-form.input wire:model="form.name" :label="__('Name')" required :hint="__('e.g. Department')" />
            <x-form.input wire:model="form.sequence" type="number" min="0" :label="__('Order')" required />
        </div>
        <x-form.select wire:model="form.parent_type_id" :label="__('Sits under')" :options="$parentTypes" :placeholder="__('Nothing (top level)')"
            :hint="__('Units of this level must be placed under a unit of that level.')" />
        <x-form.toggle wire:model="form.is_active" :label="__('Active')" />
</x-form.lookup-modal>
