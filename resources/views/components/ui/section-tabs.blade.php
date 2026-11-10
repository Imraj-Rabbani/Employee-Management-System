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

<x-ui.link-tabs :tabs="$tabs" :label="__($config['label'])" {{ $attributes }} />
