{{--
    Read-only "label: value" grid, e.g. a profile section.

    <x-ui.details :items="[
        __('Mobile') => $employee->mobile,
        __('Joined') => format_date($employee->join_date),
    ]" />

    Empty values (null / '') show a grey dash. Values are escaped; pass an HtmlString for a link or badge.
--}}
@props([
    'items' => [],
])

<dl {{ $attributes->class('grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3') }}>
    @foreach ($items as $label => $value)
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $label }}</dt>
            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                @if ($value === null || $value === '')
                    <span class="text-zinc-400">—</span>
                @else
                    {{ $value }}
                @endif
            </dd>
        </div>
    @endforeach
</dl>
