{{--
    The tabs of one sidebar section (config/sections.php), e.g. <x-ui.section-tabs section="hrm.recruitment" />.
    Tabs the user has no permission for are left out.
--}}
@props(['section'])

@php
    $config = config('sections.'.$section);
    $tabs = collect($config['tabs'])
        ->filter(fn ($tab) => ! isset($tab['can']) || auth()->user()?->can($tab['can']))
        ->map(fn ($tab) => [
            'label' => __($tab['label']),
            'href' => route($tab['route']),
            'current' => request()->routeIs(...(array) ($tab['current'] ?? $tab['route'])),
        ])
        ->all();
@endphp

<nav {{ $attributes->class('flex gap-1 overflow-x-auto border-b border-zinc-200 [scrollbar-width:none] dark:border-white/10') }} aria-label="{{ __($config['label']) }}">
    @foreach ($tabs as $tab)
        <a href="{{ $tab['href'] }}" wire:navigate @class([
            '-mb-px shrink-0 whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium transition',
            'border-brand-800 text-brand-900 dark:border-white dark:text-white' => $tab['current'],
            'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => ! $tab['current'],
        ]) @if ($tab['current']) aria-current="page" @endif>{{ $tab['label'] }}</a>
    @endforeach
</nav>
