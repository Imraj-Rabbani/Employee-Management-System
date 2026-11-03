{{--
    Shared wrapper for every x-form.* field: label (+ red * when required), hint,
    the control itself (the slot) and the validation error. Not used directly in pages.
--}}
@props([
    'label' => null,
    'hint' => null,
    'required' => false,
    'name' => null,
])

<flux:field {{ $attributes }}>
    @if ($label)
        <flux:label>
            {{ $label }}
            @if ($required)
                <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span>
            @endif
        </flux:label>
    @endif

    @if ($hint)
        <flux:description>{{ $hint }}</flux:description>
    @endif

    {{ $slot }}

    @if ($name)
        <flux:error :name="$name" />
    @endif
</flux:field>
