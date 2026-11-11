<x-form.lookup-modal :$modal :$record :heading="$record ? __('Edit device') : __('New device')"
    :deleteMessage="__('Its punches are kept. Make it inactive instead if it is only switched off for a while.')">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-form.input wire:model="form.name" :label="__('Name')" required :hint="__('e.g. Main gate')" />
        <x-form.input wire:model="form.serial_number" :label="__('Serial number')" />
    </div>
    <div class="grid gap-4 sm:grid-cols-[2fr_1fr]">
        <x-form.input wire:model="form.ip" :label="__('IP address')" required :hint="__('Reachable from the server.')" />
        <x-form.input type="number" wire:model="form.port" :label="__('Port')" required />
    </div>
    <x-form.select wire:model="form.org_unit_id" :label="__('Org unit')" :options="$units" :placeholder="__('Not tied to a unit')" />
    <x-form.toggle wire:model="form.is_active" :label="__('Active')" :hint="__('Only active devices are synced.')" />
    <flux:text class="text-sm">{{ __('The device must not have a communication password (comm key): the sync cannot send one.') }}</flux:text>

    @if ($record)
        <flux:separator />
        <div class="flex flex-wrap items-center gap-3">
            <flux:button size="sm" icon="arrow-path" wire:click="syncNow" wire:loading.attr="disabled" wire:target="syncNow">{{ __('Sync now') }}</flux:button>
            <flux:text class="text-sm" wire:loading wire:target="syncNow">{{ __('Talking to the device… (up to 15 seconds)') }}</flux:text>
            @if ($record->last_sync_error)
                <flux:text class="text-sm text-red-700 dark:text-red-300">{{ $record->last_sync_error }}</flux:text>
            @elseif ($record->last_synced_at)
                <flux:text class="text-sm">{{ __('Last synced :time', ['time' => format_datetime($record->last_synced_at)]) }}</flux:text>
            @endif
        </div>
    @endif
</x-form.lookup-modal>
