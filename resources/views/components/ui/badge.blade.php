{{--
    Small coloured label for statuses.

    <x-ui.badge color="success">Approved</x-ui.badge>
    <x-ui.badge color="warning" dot>Pending</x-ui.badge>

    Colors: neutral (default), brand, success, warning, danger, info.
--}}
@props([
    'color' => 'neutral',
    'dot' => false,
])

@php
    $colors = [
        'neutral' => 'bg-zinc-500/10 text-zinc-700 ring-zinc-500/20 dark:text-zinc-300',
        'brand' => 'bg-brand-500/10 text-brand-800 ring-brand-500/20 dark:text-brand-200',
        'success' => 'bg-green-500/10 text-green-700 ring-green-500/20 dark:text-green-300',
        'warning' => 'bg-amber-500/10 text-amber-700 ring-amber-500/25 dark:text-amber-300',
        'danger' => 'bg-red-500/10 text-red-700 ring-red-500/20 dark:text-red-300',
        'info' => 'bg-sky-500/10 text-sky-700 ring-sky-500/20 dark:text-sky-300',
    ];
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset', $colors[$color] ?? $colors['neutral']]) }}>
    @if ($dot)
        <span class="size-1.5 rounded-full bg-current"></span>
    @endif
    {{ $slot }}
</span>
