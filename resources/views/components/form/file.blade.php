{{--
    File upload with label, hint, validation error and an "Uploading…" message.
    The Livewire component needs `use WithFileUploads;`.

    <x-form.file wire:model="photo" :label="__('Photo')" accept="image/*" :hint="__('JPG or PNG, max 2 MB')" />
--}}
@props([
    'label' => null,
    'hint' => null,
    'required' => false,
])

@php $model = $attributes->wire('model')->value(); @endphp
@php $name = $model ?: $attributes->get('name'); @endphp

<x-form.field :$label :$hint :$required :$name>
    <flux:input type="file" :aria-required="$required ? 'true' : null" {{ $attributes }} />

    @if ($model)
        <flux:text wire:loading wire:target="{{ $model }}" class="mt-2 text-sm">{{ __('Uploading…') }}</flux:text>
    @endif
</x-form.field>
