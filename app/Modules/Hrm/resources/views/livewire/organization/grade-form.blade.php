<x-form.lookup-modal :$modal :$record :heading="$record ? __('Edit grade') : __('New grade')">
        <div class="grid gap-4 sm:grid-cols-3">
            <x-form.input wire:model="form.code" :label="__('Code')" />
            <x-form.input wire:model="form.name" :label="__('Name')" required />
            <x-form.input wire:model="form.sequence" type="number" min="0" :label="__('Order')" required :hint="__('Lists are sorted by this')" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input wire:model="form.salary_min" type="number" min="0" step="0.01" :label="__('Salary from')" :hint="__('Monthly gross. Offers are compared with this band.')" />
            <x-form.input wire:model="form.salary_max" type="number" min="0" step="0.01" :label="__('Salary to')" />
        </div>
        <x-form.textarea wire:model="form.description" :label="__('Description')" />
        <x-form.toggle wire:model="form.is_active" :label="__('Active')" />
</x-form.lookup-modal>
