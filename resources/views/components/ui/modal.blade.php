{{--
    Centred dialog with a solid background. Used for create/edit forms.

    <x-ui.modal name="employee-form" heading="Add employee">
        ...form fields...
        <x-slot:footer>
            <flux:button wire:click="save" variant="primary">Save</flux:button>
        </x-slot:footer>
    </x-ui.modal>

    Open:   <flux:modal.trigger name="employee-form"><flux:button>Add</flux:button></flux:modal.trigger>
    Close from Livewire after saving:   Flux::modal('employee-form')->close();

    size: md (default, one column of fields) or lg (wider, for two-column forms).
--}}
@props([
    'name',
    'heading' => null,
    'subheading' => null,
    'size' => 'md',
])

<flux:modal :name="$name" {{ $attributes->class([
    'w-full rounded-2xl dark:!bg-brand-900',
    'md:max-w-lg' => $size === 'md',
    'md:max-w-3xl' => $size === 'lg',
]) }}>
    <div class="space-y-6">
        @if ($heading)
            <div>
                <flux:heading size="lg">{{ $heading }}</flux:heading>

                @if ($subheading)
                    <flux:text class="mt-1">{{ $subheading }}</flux:text>
                @endif
            </div>
        @endif

        <div class="space-y-5">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="flex justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-white/10">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                {{ $footer }}
            </div>
        @endisset
    </div>
</flux:modal>
