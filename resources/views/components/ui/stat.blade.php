{{--
    Number tile for dashboards.

    <x-ui.stat label="Employees" value="240" icon="users" trend="+4 this month" :trend-up="true" />

    trend-up: true = green arrow up, false = red arrow down, null = no arrow.
--}}
@props([
    'label',
    'value',
    'icon' => null,
    'trend' => null,
    'trendUp' => null,
])

<div {{ $attributes->class('glass rounded-2xl p-5') }}>
    <div class="flex items-center justify-between gap-4">
        <flux:text class="font-medium">{{ $label }}</flux:text>

        @if ($icon)
            <div class="flex size-9 items-center justify-center rounded-xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                <flux:icon :name="$icon" variant="mini" />
            </div>
        @endif
    </div>

    <div class="mt-2 text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ $value }}</div>

    @if ($trend)
        <div @class([
            'mt-1 flex items-center gap-1 text-sm',
            'text-green-600 dark:text-green-400' => $trendUp === true,
            'text-red-600 dark:text-red-400' => $trendUp === false,
            'text-zinc-500 dark:text-zinc-400' => $trendUp === null,
        ])>
            @if ($trendUp === true)
                <flux:icon.arrow-trending-up variant="micro" />
            @elseif ($trendUp === false)
                <flux:icon.arrow-trending-down variant="micro" />
            @endif
            {{ $trend }}
        </div>
    @endif
</div>
