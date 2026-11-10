<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="isolate min-h-screen bg-brand-50 dark:bg-brand-950">
        {{-- Soft colour blobs behind the glass panels. Glass only looks like glass if something colourful is behind it. --}}
        <div aria-hidden="true" class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
            <div class="absolute -top-40 -left-32 size-[36rem] rounded-full bg-brand-300/50 blur-3xl dark:bg-brand-600/40"></div>
            <div class="absolute top-1/3 -right-40 size-[32rem] rounded-full bg-ember-200/50 blur-3xl dark:bg-ember-800/30"></div>
            <div class="absolute -bottom-40 left-1/3 size-[30rem] rounded-full bg-brand-200/60 blur-3xl dark:bg-brand-700/40"></div>
        </div>

        {{-- @persist: the sidebar is kept as it is when a wire:navigate link changes the page (no redraw, scroll
             position and open groups stay). It is not re-rendered, so the highlight of the current item is set in
             the browser (resources/js/app.js) from each item's data-nav-paths. --}}
        @php
            // URL paths an item stands for, from its 'current' route-name pattern(s): "hrm/employees/{employee}"
            // becomes "/hrm/employees/" (= everything below it). wire:current.ignore stops Livewire's own
            // exact-URL highlighting from undoing ours.
            $navPaths = fn (string|array $patterns) => collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutesByName())
                ->filter(fn ($route, $name) => \Illuminate\Support\Str::is($patterns, $name))
                ->map(fn ($route) => '/'.\Illuminate\Support\Str::before($route->uri(), '{'))
                ->unique()->values()->toJson();
        @endphp
        @persist('sidebar')
        <flux:sidebar sticky stashable class="glass !border-y-0 !border-l-0">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('home') }}" class="mr-5 flex items-center space-x-2" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.item icon="home" :href="route('home')" :current="request()->routeIs('dashboard')" :data-nav-paths="$navPaths('dashboard')" wire:current.ignore wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>

                {{-- Platform owners only (Gate "super-admin" in AppServiceProvider). --}}
                @can('super-admin')
                    <flux:navlist.item icon="building-office-2" :href="route('admin.dashboard')" :current="request()->routeIs('admin.*')" :data-nav-paths="$navPaths('admin.*')" wire:current.ignore wire:navigate>{{ __('Platform admin') }}</flux:navlist.item>
                @endcan
            </flux:navlist>

            {{-- Self Service + modules, defined in config/navigation.php --}}
            <flux:navlist variant="outline">
                @foreach (config('navigation') as $group)
                    {{-- Skip modules the user's company has not switched on. (auth()->user()->tenant, not tenant():
                         pages like /dashboard don't run the "tenant" middleware.) --}}
                    @continue(isset($group['module']) && ! auth()->user()?->tenant?->hasModule($group['module']))

                    <flux:navlist.group expandable :heading="__($group['heading'])" :expanded="request()->routeIs($group['active'])" class="grid">
                        @foreach ($group['items'] as $item)
                            {{-- Hide items the user has no permission for. A list of permissions = any one of them. --}}
                            @continue(isset($item['can']) && ! auth()->user()?->canAny((array) $item['can']))

                            @if ($item['route'])
                                <flux:navlist.item :href="route($item['route'])" :current="request()->routeIs($item['current'] ?? $item['route'])" :data-nav-paths="$navPaths($item['current'] ?? $item['route'])" wire:current.ignore wire:navigate>{{ __($item['label']) }}</flux:navlist.item>
                            @else
                                <flux:navlist.item badge="Soon" class="pointer-events-none opacity-60">{{ __($item['label']) }}</flux:navlist.item>
                            @endif
                        @endforeach
                    </flux:navlist.group>
                @endforeach
            </flux:navlist>
        </flux:sidebar>
        @endpersist

        {{-- Topbar: page title, dark mode toggle and user menu. Shown on every screen size. --}}
        <flux:header class="glass sticky top-0 z-20 !border-x-0 !border-t-0">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            {{-- Desktop: show / hide the sidebar completely (resources/js/app.js). --}}
            <flux:tooltip :content="__('Show / hide menu')" position="bottom">
                <flux:button variant="subtle" square icon="bars-2" class="-ml-2 mr-2 max-lg:hidden" onclick="emsSidebar.toggle()" aria-label="{{ __('Show / hide menu') }}" />
            </flux:tooltip>

            <flux:heading size="lg" class="truncate">{{ $title ?? config('app.name') }}</flux:heading>

            <flux:spacer />

            {{-- In-app notifications (approvals ...). Only inside a company: they link to company pages. --}}
            @if (tenant())
                <livewire:core::notification-bell />
            @endif

            {{-- $flux.dark is Flux's Alpine helper. It switches the theme and remembers the choice. --}}
            <flux:button x-data x-on:click="$flux.dark = ! $flux.dark" variant="subtle" square class="mr-2" aria-label="{{ __('Toggle dark mode') }}">
                <flux:icon.moon variant="mini" x-show="! $flux.dark" />
                <flux:icon.sun variant="mini" x-show="$flux.dark" x-cloak />
            </flux:button>

            <flux:dropdown position="bottom" align="end">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu class="w-[220px]">
                    <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-800 text-white dark:bg-brand-200 dark:text-brand-950">
                            {{ auth()->user()->initials() }}
                        </span>

                        <div class="grid flex-1 leading-tight">
                            <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                            <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                        </div>
                    </div>

                    <flux:menu.separator />

                    <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        {{-- Drag handle on the sidebar's right edge (desktop). Drag to resize, drag far left to close,
             double-click for the default width. Outside the sidebar: Flux's grid needs sidebar + header side by side. --}}
        <div data-sidebar-handle role="separator" aria-orientation="vertical" tabindex="0"
            aria-label="{{ __('Resize menu') }}" title="{{ __('Drag to resize, double-click to reset') }}"
            onpointerdown="emsSidebar.drag(event)" ondblclick="emsSidebar.reset()" onkeydown="emsSidebar.key(event)"
            class="group fixed inset-y-0 z-30 hidden w-1.5 cursor-col-resize touch-none lg:block">
            <div class="mx-auto h-full w-0.5 bg-transparent transition group-hover:bg-brand-400 group-focus-visible:bg-brand-400 group-active:bg-brand-500"></div>
        </div>

        <x-ui.toast />
        <x-ui.confirm />

        @fluxScripts
    </body>
</html>
