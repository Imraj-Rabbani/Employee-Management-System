<x-ui.card :heading="__('Leave year and earning')" :subheading="__('Balances, carry forward and limits count per leave year. Now: :year.', ['year' => $current->label()])">
    <form wire:submit="save" class="flex flex-wrap items-end gap-6">
        <x-form.select wire:model="startMonth" :options="$months" class="w-44" :label="__('Year starts in')" :disabled="$locked"
            :hint="$locked ? __('Fixed: leave balances already exist.') : __('January = the calendar year.')" />
        <x-form.date wire:model="earnFrom" class="w-44" :label="__('Earn leave from')"
            :hint="__('Go-live date: earlier periods are in the opening balances.')" />
        <x-form.input type="number" min="0" max="90" wire:model="closeAfterDays" class="w-36" :label="__('Close the year after (days)')"
            :hint="__('Then carry forward / expire.')" />
        <flux:button type="submit" size="sm" variant="primary">{{ __('Save') }}</flux:button>
    </form>
</x-ui.card>
