<x-form.lookup-modal :$modal :$record :heading="$record ? __('Edit rejection reason') : __('New rejection reason')">
        <x-form.input wire:model="form.name" :label="__('Name')" required />
        <x-form.toggle wire:model="form.is_active" :label="__('Active')" />
</x-form.lookup-modal>
