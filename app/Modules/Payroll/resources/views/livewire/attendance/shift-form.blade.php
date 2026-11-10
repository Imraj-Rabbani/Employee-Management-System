<x-form.lookup-modal :$modal :$record size="lg" :heading="$record ? __('Edit shift') : __('New shift')">
    <div class="grid gap-4 sm:grid-cols-[1fr_2fr]">
        <x-form.input wire:model="form.code" :label="__('Code')" required />
        <x-form.input wire:model="form.name" :label="__('Name')" required :hint="__('e.g. General, Morning, Night')" />
    </div>
    <x-form.select wire:model="form.org_unit_id" :label="__('Org unit')" :options="$units" :placeholder="__('Whole company')"
        :hint="__('Leave empty to offer this shift everywhere.')" />

    <flux:separator :text="__('Working hours')" />
    <div class="grid gap-4 sm:grid-cols-3">
        <x-form.input type="time" wire:model="form.start_time" :label="__('Start')" required />
        <x-form.input type="time" wire:model="form.end_time" :label="__('End')" required :hint="__('Before the start = ends the next day.')" />
        <x-form.input type="number" step="0.25" wire:model="form.hours" :label="__('Hours')" required />
    </div>

    <flux:separator :text="__('Late arrival')" />
    <div class="grid gap-4 sm:grid-cols-3">
        <x-form.input type="number" wire:model="form.grace_minutes" :label="__('Grace (minutes)')" required :hint="__('Still on time within this.')" />
        <x-form.input type="number" wire:model="form.grace_limit_per_month" :label="__('Grace per month')" :hint="__('Times allowed. Empty = always.')" />
        <x-form.input type="number" wire:model="form.flexibility_minutes" :label="__('Flexibility (minutes)')" :hint="__('May start later if staying later.')" />
    </div>

    <flux:separator :text="__('Half day')" />
    <div class="grid gap-4 sm:grid-cols-2">
        <x-form.input type="number" step="0.25" wire:model="form.half_day_hours" :label="__('Half day below (hours)')" :hint="__('Worked less than this = half day.')" />
        <x-form.input type="time" wire:model="form.half_day_after" :label="__('Half day if in after')" />
    </div>

    <flux:separator :text="__('Web punch location')" />
    <x-form.toggle wire:model="form.geofence_enabled" :label="__('Only punch near the office')" :hint="__('Web / mobile punches must be within the radius (step 7.3).')" />
    <div x-show="$wire.form.geofence_enabled" x-cloak class="grid gap-4 sm:grid-cols-3">
        <x-form.input type="number" step="any" wire:model="form.geofence_lat" :label="__('Latitude')" />
        <x-form.input type="number" step="any" wire:model="form.geofence_lng" :label="__('Longitude')" />
        <x-form.input type="number" wire:model="form.geofence_radius_m" :label="__('Radius (m)')" />
    </div>

    <flux:separator />
    <x-form.toggle wire:model="form.is_default" :label="__('Default shift')" :hint="__('Used for employees without a shift. Only one shift can be the default.')" />
    <x-form.toggle wire:model="form.is_active" :label="__('Active')" />
</x-form.lookup-modal>
