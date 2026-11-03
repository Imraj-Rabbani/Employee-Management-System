{{--
    Glass card: the basic box most screens are built from.

    <x-ui.card heading="Employees" subheading="All active staff">
        <x-slot:actions>
            <flux:button size="sm">Add</flux:button>
        </x-slot:actions>

        ...content...

        <x-slot:footer>Showing 10 of 240</x-slot:footer>
    </x-ui.card>

    Every part except the content is optional. Use :padding="false" for tables
    that should touch the card edges.
--}}
@props([
    'heading' => null,
    'subheading' => null,
    'padding' => true,
])

<div {{ $attributes->class('glass overflow-hidden rounded-2xl') }}>
    @if ($heading || isset($actions))
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                @if ($heading)
                    <flux:heading size="lg">{{ $heading }}</flux:heading>
                @endif

                @if ($subheading)
                    <flux:text class="mt-1">{{ $subheading }}</flux:text>
                @endif
            </div>

            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div @class(['p-6' => $padding])>
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="border-t border-white/50 px-6 py-4 text-sm text-zinc-600 dark:border-white/10 dark:text-zinc-300">
            {{ $footer }}
        </div>
    @endisset
</div>
