<div class="glass overflow-hidden rounded-2xl">
    <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
        <div>
            <flux:heading size="lg">{{ __('Web punch (Self Service)') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Employees punch in the browser on My Attendance. The place is checked against their shift\'s geofence.') }}</flux:text>
        </div>
    </div>

    <div class="p-6">
        <form wire:submit="save" class="flex flex-wrap items-end gap-6">
            <flux:switch wire:model="webPunch" :label="__('Allow web punch')" />

            <flux:switch wire:model="requireLocation" :label="__('Require location')" :description="__('Refuse a punch when the browser does not share its location.')" />
            <flux:button type="submit" size="sm" variant="primary">{{ __('Save') }}</flux:button>
        </form>
    </div>
</div>
