{{--
    Two-column layout for login, sign-up and the other auth pages (used through <x-layouts.auth>).
    Left (large screens only): black brand panel, logo in the middle, a slowly moving grid and drifting glows.
    Right: the page's form. Everything fits one screen (h-dvh); the form column scrolls only on very short screens.
    Animations stop when the visitor asks the system for reduced motion.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <style>
            @keyframes auth-drift-a { 0%, 100% { transform: translate(0, 0) scale(1); } 50% { transform: translate(60px, 40px) scale(1.15); } }
            @keyframes auth-drift-b { 0%, 100% { transform: translate(0, 0) scale(1.1); } 50% { transform: translate(-50px, -60px) scale(0.95); } }
            @keyframes auth-grid { from { background-position: 0 0; } to { background-position: 48px 48px; } }
            @keyframes auth-rise { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
            .auth-drift-a { animation: auth-drift-a 18s ease-in-out infinite; }
            .auth-drift-b { animation: auth-drift-b 22s ease-in-out infinite; }
            .auth-grid { animation: auth-grid 6s linear infinite; }
            .auth-rise { animation: auth-rise .8s ease-out both; }
            @media (prefers-reduced-motion: reduce) {
                .auth-drift-a, .auth-drift-b, .auth-grid, .auth-rise { animation: none; }
            }
        </style>
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-neutral-950">
        <div class="grid h-dvh lg:grid-cols-2">
            {{-- Left: brand panel --}}
            <aside class="relative hidden overflow-hidden bg-black text-white lg:flex lg:flex-col lg:items-center lg:justify-center lg:p-12">
                {{-- Two soft glows (navy + a hint of the logo red) drifting slowly, over a faint moving grid. --}}
                <div class="auth-drift-a pointer-events-none absolute -left-24 -top-24 size-[28rem] rounded-full bg-brand-600/30 blur-3xl"></div>
                <div class="auth-drift-b pointer-events-none absolute -bottom-32 -right-24 size-[26rem] rounded-full bg-ember-600/15 blur-3xl"></div>
                <div class="auth-grid pointer-events-none absolute inset-0 opacity-[0.12]" style="background-image: linear-gradient(rgba(255,255,255,.5) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.5) 1px, transparent 1px); background-size: 48px 48px;"></div>

                <div class="relative z-10 flex max-w-md flex-col items-center text-center">
                    {{-- The logo is navy on transparent: a white chip keeps it readable on black. --}}
                    <a href="{{ route('home') }}" class="mb-10 rounded-2xl bg-white px-6 py-4 shadow-2xl shadow-brand-900/40" wire:navigate aria-label="{{ config('app.name') }} home">
                        <img src="{{ asset('images/erp2all-logo.png') }}" alt="{{ config('app.name') }}" class="h-16 w-auto">
                    </a>

                    <h2 class="auth-rise text-4xl font-semibold leading-tight tracking-tight">{{ __('Your people, payroll and approvals in one place.') }}</h2>
                    <p class="auth-rise mt-4 text-base text-neutral-400" style="animation-delay: .15s">{{ __('Simple HR for growing teams. Start free, upgrade when you grow.') }}</p>

                    {{-- What's inside: fixed tags (no animation). --}}
                    <div class="mt-10 flex flex-wrap justify-center gap-3">
                        @foreach ([__('Employees'), __('Attendance'), __('Leave'), __('Payroll'), __('Approvals')] as $i => $chip)
                            <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3.5 py-1.5 text-sm text-neutral-200 backdrop-blur">
                                <span class="size-1.5 rounded-full {{ $i % 2 ? 'bg-ember-500' : 'bg-brand-300' }}"></span>
                                {{ $chip }}
                            </span>
                        @endforeach
                    </div>
                </div>

                <p class="absolute bottom-8 z-10 text-xs text-neutral-500">&copy; {{ date('Y') }} {{ config('app.name') }}</p>
            </aside>

            {{-- Right: the form --}}
            <main class="flex items-center justify-center overflow-y-auto px-6 py-8">
                <div class="w-full max-w-sm">
                    <a href="{{ route('home') }}" class="mb-6 flex justify-center lg:hidden" wire:navigate aria-label="{{ config('app.name') }} home">
                        <x-app-logo />
                    </a>
                    {{ $slot }}
                </div>
            </main>
        </div>
        @fluxScripts
    </body>
</html>
