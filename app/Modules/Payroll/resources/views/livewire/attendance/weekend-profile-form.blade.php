<x-form.lookup-modal :$modal :$record :heading="$record ? __('Edit weekend profile') : __('New weekend profile')">
    <div class="grid gap-4 sm:grid-cols-[1fr_2fr]">
        <x-form.input wire:model="form.code" :label="__('Code')" />
        <x-form.input wire:model="form.name" :label="__('Name')" required :hint="__('e.g. Friday & Saturday')" />
    </div>

    <flux:separator :text="__('Days off')" />
    <div class="grid gap-4 sm:grid-cols-2">
        <x-form.select wire:model="form.day_one" :label="__('First weekend day')" :options="$days" :placeholder="__('None')" />
        <x-form.select wire:model="form.alternate_one" :label="__('Weeks')" :options="$parities" :placeholder="__('Every week')" />
        <x-form.select wire:model="form.day_two" :label="__('Second weekend day')" :options="$days" :placeholder="__('None')" />
        <x-form.select wire:model="form.alternate_two" :label="__('Weeks')" :options="$parities" :placeholder="__('Every week')" />
    </div>
    <flux:text class="text-sm">{{ __('Odd / even counts that weekday from 1 January: the 1st, 3rd, 5th … Saturday of the year are odd.') }}</flux:text>

    <x-form.select wire:model="form.home_office_day" :label="__('Home office day')" :options="$days" :placeholder="__('None')" />
    <x-form.textarea wire:model="form.detail" :label="__('Detail')" rows="2" />
    <x-form.toggle wire:model="form.is_active" :label="__('Active')" />
</x-form.lookup-modal>
