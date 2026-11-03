{{--
    Date picker (the browser's own), value is "YYYY-MM-DD".

    <x-form.date wire:model="joined_on" :label="__('Joining date')" max="{{ now()->toDateString() }}" />
--}}
@props([
    'label' => null,
    'hint' => null,
    'required' => false,
])

@php $name = $attributes->wire('model')->value() ?: $attributes->get('name'); @endphp

<x-form.field :$label :$hint :$required :$name>
    <flux:input type="date" :aria-required="$required ? 'true' : null" {{ $attributes }} />
</x-form.field>
