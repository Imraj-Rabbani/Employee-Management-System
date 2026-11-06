<x-form.lookup-modal :$modal :$record :heading="$record ? __('Edit branch') : __('New branch')">
        <x-form.input wire:model="form.name" :label="__('Name')" required />
        <x-form.input wire:model="form.location" :label="__('Location')" />
        <x-form.toggle wire:model="form.is_active" :label="__('Active')" />
</x-form.lookup-modal>
