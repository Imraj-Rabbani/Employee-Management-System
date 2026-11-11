<x-form.lookup-modal :$modal :$record :heading="$record ? __('Edit holiday calendar') : __('New holiday calendar')"
    :deleteMessage="__('Its holidays stay; only the calendar is removed. Not possible while employees use it.')">
    <x-form.input wire:model="form.name" :label="__('Name')" required :hint="__('e.g. Head office, Factory')" />
    <x-form.textarea wire:model="form.detail" :label="__('Detail')" rows="2" />
    <x-form.toggle wire:model="form.is_active" :label="__('Active')" />
    @if ($record)
        <flux:text class="text-sm">{{ __('Choose its holidays on the Holidays tab (open a holiday, tick this calendar).') }}</flux:text>
    @endif
</x-form.lookup-modal>
