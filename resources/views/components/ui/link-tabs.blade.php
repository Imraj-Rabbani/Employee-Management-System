{{--
    A row of LINKS styled like tabs: each tab is its own page (bookmarkable URL).
    (x-ui.tabs is the other kind: panes on one page, switched with Alpine.)

        <x-ui.link-tabs :label="__('Reports')" :tabs="[
            ['label' => __('Funnel'), 'href' => route('...'), 'current' => true],
            ...
        ]" />

    size="sm" = a smaller second row under the section tabs (e.g. Recruitment → Reports → Funnel).
--}}
@props(['tabs' => [], 'label' => null, 'size' => 'md'])

<nav {{ $attributes->class('flex gap-1 overflow-x-auto border-b border-zinc-200 [scrollbar-width:none] dark:border-white/10') }} @if ($label) aria-label="{{ $label }}" @endif>
    @foreach ($tabs as $tab)
        <a href="{{ $tab['href'] }}" wire:navigate @class([
            '-mb-px shrink-0 whitespace-nowrap border-b-2 font-medium transition',
            'px-3 py-2 text-sm' => $size === 'md',
            'px-2.5 py-1.5 text-xs' => $size === 'sm',
            'border-brand-800 text-brand-900 dark:border-white dark:text-white' => $tab['current'],
            'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => ! $tab['current'],
        ]) @if ($tab['current']) aria-current="page" @endif>{{ $tab['label'] }}</a>
    @endforeach
</nav>
