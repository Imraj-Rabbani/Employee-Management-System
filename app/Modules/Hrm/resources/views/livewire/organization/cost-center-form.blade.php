<x-form.lookup-modal :$modal :$record :heading="$record ? __('Edit cost center') : __('New cost center')">
        <div class="grid gap-4 sm:grid-cols-[1fr_2fr]">
            <x-form.input wire:model="form.code" :label="__('Code')" required />
            <x-form.input wire:model="form.name" :label="__('Name')" required />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input wire:model="form.function_code" :label="__('Function code')" />
            <x-form.input wire:model="form.account_code" :label="__('GL account')" :hint="__('Used by the accounting export')" />
        </div>
        <x-form.toggle wire:model="form.is_active" :label="__('Active')" />
</x-form.lookup-modal>
