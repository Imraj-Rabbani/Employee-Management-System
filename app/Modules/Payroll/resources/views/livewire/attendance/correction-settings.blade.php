<x-ui.card :heading="__('Limits for employees (Self Service)')" :subheading="__('0 = no limit. Corrections asked by HR are not limited.')">
    <form wire:submit="save" class="flex flex-wrap items-end gap-6">
        <x-form.input wire:model="daysBack" type="number" min="0" max="366" class="w-28" :label="__('Days back')" :hint="__('How old a day may be.')" />
        <x-form.input wire:model="monthlyLimit" type="number" min="0" max="31" class="w-28" :label="__('Per month')" :hint="__('Rejected ones do not count.')" />
        <flux:button type="submit" size="sm" variant="primary">{{ __('Save') }}</flux:button>
    </form>
</x-ui.card>
