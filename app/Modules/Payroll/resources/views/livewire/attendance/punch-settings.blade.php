<x-ui.card :heading="__('Web punch (Self Service)')" :subheading="__('Employees punch in the browser on My Attendance. The place is checked against their shift\'s geofence.')">
    <form wire:submit="save" class="flex flex-wrap items-end gap-6">
        <x-form.toggle wire:model="webPunch" :label="__('Allow web punch')" />
        <x-form.toggle wire:model="requireLocation" :label="__('Require location')" :hint="__('Refuse a punch when the browser does not share its location.')" />
        <flux:button type="submit" size="sm" variant="primary">{{ __('Save') }}</flux:button>
    </form>
</x-ui.card>
