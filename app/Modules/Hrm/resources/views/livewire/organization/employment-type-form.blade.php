<x-form.lookup-modal :$modal :$record :heading="$record ? __('Edit employment type') : __('New employment type')">
        <x-form.input wire:model="form.name" :label="__('Name')" required :hint="__('e.g. Permanent, Contract')" />
        <x-form.toggle wire:model="form.is_active" :label="__('Active')" />
</x-form.lookup-modal>
