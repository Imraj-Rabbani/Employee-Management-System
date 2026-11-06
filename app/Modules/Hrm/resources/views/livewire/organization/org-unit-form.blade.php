<x-form.lookup-modal :$modal :$record size="lg" :heading="$record ? __('Edit org unit') : __('New org unit')">
    <div class="grid gap-4 sm:grid-cols-[2fr_1fr]">
        <x-form.input wire:model="form.name" :label="__('Name')" required :hint="__('e.g. Accounts')" />
        <x-form.input wire:model="form.code" :label="__('Code')" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-form.select wire:model="form.org_unit_type_id" :label="__('Level')" :options="$types" :placeholder="__('Choose…')" required />
        <x-form.select wire:model="form.parent_id" :label="__('Under')" :options="$parents" :placeholder="__('Nothing (top level)')" />
    </div>

    <x-form.select wire:model="form.cost_center_id" :label="__('Cost center')" :options="$costCenters" :placeholder="__('None')" />

    <x-form.toggle wire:model="form.is_active" :label="__('Active')" />

    @if ($record)
        <flux:text class="text-xs">{{ __('The unit head is chosen once employees exist (Phase 5).') }}</flux:text>
    @endif
</x-form.lookup-modal>
