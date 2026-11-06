<x-form.lookup-modal :$modal :$record :heading="$record ? __('Edit grade') : __('New grade')">
        <div class="grid gap-4 sm:grid-cols-3">
            <x-form.input wire:model="form.code" :label="__('Code')" />
            <x-form.input wire:model="form.name" :label="__('Name')" required />
            <x-form.input wire:model="form.sequence" type="number" min="0" :label="__('Order')" required :hint="__('Lists are sorted by this')" />
        </div>
        <x-form.textarea wire:model="form.description" :label="__('Description')" />
        <x-form.toggle wire:model="form.is_active" :label="__('Active')" />
</x-form.lookup-modal>
