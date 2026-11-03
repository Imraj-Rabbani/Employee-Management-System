{{--
    Multi-line text with label, hint and validation error.

    <x-form.textarea wire:model="notes" :label="__('Notes')" rows="4" />
--}}
@props([
    'label' => null,
    'hint' => null,
    'required' => false,
])

@php $name = $attributes->wire('model')->value() ?: $attributes->get('name'); @endphp

<x-form.field :$label :$hint :$required :$name>
    <flux:textarea :aria-required="$required ? 'true' : null" rows="3" {{ $attributes }} />
</x-form.field>
