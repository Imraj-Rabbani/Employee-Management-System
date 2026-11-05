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

        <flux:sidebar sticky stashable class="glass !border-y-0 !border-l-0">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('home') }}" class="mr-5 flex items-center space-x-2" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.item icon="home" :href="route('home')" :current="request()->routeIs('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>

                {{-- Platform owners only (Gate "super-admin" in AppServiceProvider). --}}
                @can('super-admin')
                    <flux:navlist.item icon="building-office-2" :href="route('admin.dashboard')" :current="request()->routeIs('admin.*')" wire:navigate>{{ __('Platform admin') }}</flux:navlist.item>
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
                            @if ($item['route'])
                                <flux:navlist.item :href="route($item['route'])" :current="request()->routeIs($item['route'])" wire:navigate>{{ __($item['label']) }}</flux:navlist.item>
                            @else
                                <flux:navlist.item badge="Soon" class="pointer-events-none opacity-60">{{ __($item['label']) }}</flux:navlist.item>
                            @endif
                        @endforeach
                    </flux:navlist.group>
                @endforeach
            </flux:navlist>
        </flux:sidebar>

        {{-- Topbar: page title, dark mode toggle and user menu. Shown on every screen size. --}}
        <flux:header class="glass sticky top-0 z-20 !border-x-0 !border-t-0">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:heading size="lg" class="truncate">{{ $title ?? config('app.name') }}</flux:heading>

            <flux:spacer />

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

        <x-ui.toast />
        <x-ui.confirm />

        @fluxScripts
    </body>
</html>
