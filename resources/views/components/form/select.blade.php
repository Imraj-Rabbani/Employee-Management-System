{{--
    Dropdown with label, hint and validation error.

    <x-form.select wire:model="department" :label="__('Department')" :options="$departments" :placeholder="__('Choose…')" />

    options: [value => text], e.g. Department::pluck('name', 'id') or an enum's options.
    Extra <option>s can also go between the tags.
--}}
@props([
    'label' => null,
    'hint' => null,
    'required' => false,
    'options' => [],
    'placeholder' => null,
])

@php $name = $attributes->wire('model')->value() ?: $attributes->get('name'); @endphp

<x-form.field :$label :$hint :$required :$name>
    <flux:select :aria-required="$required ? 'true' : null" {{ $attributes }}>
        @if ($placeholder)
            <flux:select.option value="">{{ $placeholder }}</flux:select.option>
        @endif

        @foreach ($options as $value => $text)
            <flux:select.option :value="$value">{{ $text }}</flux:select.option>
        @endforeach

        {{ $slot }}
    </flux:select>
</x-form.field>
