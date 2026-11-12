<x-ui.card :heading="__('Leave year')" :subheading="__('Balances, carry forward and limits count per leave year. Now: :year.', ['year' => $current->label()])">
    <form wire:submit="save" class="flex flex-wrap items-end gap-6">
        <x-form.select wire:model="startMonth" :options="$months" class="w-48" :label="__('Starts in')" :disabled="$locked"
            :hint="$locked ? __('Fixed: leave balances already exist.') : __('January = the calendar year.')" />
        @unless ($locked)
            <flux:button type="submit" size="sm" variant="primary">{{ __('Save') }}</flux:button>
        @endunless
    </form>
</x-ui.card>
