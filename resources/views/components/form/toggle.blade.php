{{--
    On/off switch for yes/no fields.

    <x-form.toggle wire:model="active" :label="__('Active')" :hint="__('Inactive employees are hidden from lists')" />
--}}
@props([
    'label' => null,
    'hint' => null,
])

<flux:switch :label="$label" :description="$hint" {{ $attributes }} />
