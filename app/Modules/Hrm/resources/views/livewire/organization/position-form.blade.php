<x-form.lookup-modal :$modal :$record size="lg" :heading="$record ? __('Edit position') : __('New position')">
    <div class="grid gap-4 sm:grid-cols-[2fr_1fr]">
        <x-form.input wire:model="form.name" :label="__('Name')" required :hint="__('e.g. Accounts Officer')" />
        <x-form.input wire:model="form.code" :label="__('Code')" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-form.select wire:model="form.org_unit_id" :label="__('Org unit')" :options="$units" :placeholder="__('Choose…')" required />
        <x-form.select wire:model="form.grade_id" :label="__('Grade')" :options="$grades" :placeholder="__('None')" />
        <x-form.select wire:model="form.parent_id" :label="__('Reports to')" :options="$bosses" :placeholder="__('Nobody (top position)')" />
        <x-form.select wire:model="form.cost_center_id" :label="__('Cost center')" :options="$costCenters" :placeholder="__('Same as the unit')" />
        <x-form.date wire:model="form.effective_from" :label="__('Effective from')" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-form.toggle wire:model="form.is_vacant" :label="__('Vacant')" :hint="__('Filled automatically once employees exist (Phase 5).')" />
        <x-form.toggle wire:model="form.is_active" :label="__('Active')" />
    </div>
</x-form.lookup-modal>
