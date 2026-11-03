{{--
    Text input with label, hint and validation error.

    <x-form.input wire:model="name" :label="__('Full name')" required />
    <x-form.input wire:model="email" type="email" :label="__('Email')" :hint="__('Used for login')" />

    The error message for the wire:model field appears automatically.
--}}
@props([
    'label' => null,
    'hint' => null,
    'required' => false,
])

@php $name = $attributes->wire('model')->value() ?: $attributes->get('name'); @endphp

<x-form.field :$label :$hint :$required :$name>
    <flux:input :aria-required="$required ? 'true' : null" {{ $attributes }} />
</x-form.field>
