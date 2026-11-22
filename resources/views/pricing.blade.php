<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head', ['title' => 'Pricing | '.config('app.name', 'ERP2ALL')])
    </head>
    <body class="min-h-screen bg-brand-950 text-white antialiased">
        <div class="relative isolate min-h-screen overflow-hidden">
            <div class="absolute inset-0 -z-20 bg-[radial-gradient(circle_at_top_right,_rgba(191,59,43,0.16),transparent_32rem),radial-gradient(circle_at_bottom_left,_rgba(77,104,132,0.22),transparent_34rem)]"></div>
            <div class="absolute inset-0 -z-10 opacity-[0.07]" style="background-image: linear-gradient(rgba(255,255,255,.25) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.25) 1px, transparent 1px); background-size: 48px 48px;"></div>

            <header class="mx-auto flex max-w-7xl items-center justify-between px-6 py-6 lg:px-8">
                <a href="{{ route('home') }}" class="flex items-center" wire:navigate aria-label="{{ config('app.name') }} home">
                    <x-app-logo />
                </a>

                <nav class="flex items-center gap-2 sm:gap-3">
                    <a href="{{ route('home') }}#features" wire:navigate class="hidden rounded-lg px-3 py-2.5 text-sm font-semibold text-brand-100 transition hover:text-white sm:inline-flex">
                        Features
                    </a>
                    <a href="{{ route('pricing') }}" class="rounded-lg px-3 py-2.5 text-sm font-semibold text-white" aria-current="page">
                        Pricing
                    </a>
                    <a href="{{ route('login') }}" class="rounded-lg px-3 py-2.5 text-sm font-semibold text-brand-100 transition hover:text-white">
                        Log in
                    </a>
                    <a href="{{ route('register') }}" class="rounded-lg bg-ember-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-ember-500">
                        Register
                    </a>
                </nav>
            </header>

            <main>
                <section class="mx-auto max-w-7xl px-6 pb-20 pt-12 lg:px-8 lg:pb-28 lg:pt-16">
                    <div class="mx-auto max-w-3xl text-center">
                        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-ember-400">Simple pricing</p>
                        <h1 class="mt-3 text-4xl font-semibold tracking-tight text-white sm:text-5xl">Choose what fits your team</h1>
                        <p class="mt-5 text-lg leading-8 text-brand-300">
                            Start free for a small team, scale as you grow, or get a solution built around your organization.
                        </p>
                    </div>

                    <div class="mt-12 grid items-stretch gap-5 lg:grid-cols-3">
                        <article class="flex flex-col rounded-2xl border border-brand-700/70 bg-brand-900/45 p-7">
                            <p class="text-sm font-semibold uppercase tracking-wider text-brand-300">Free</p>
                            <div class="mt-5">
                                <span class="text-5xl font-semibold tracking-tight">Free</span>
                            </div>
                            <p class="mt-3 min-h-12 text-sm leading-6 text-brand-300">For small teams getting started.</p>

                            <div class="my-7 h-px bg-white/10"></div>
                            <ul class="flex flex-1 flex-col gap-4 text-sm text-brand-100">
                                <li class="flex gap-3">
                                    <span class="mt-1 size-1.5 shrink-0 rounded-full bg-brand-300"></span>
                                    <span><strong class="text-white">Up to 10 users</strong></span>
                                </li>
                                <li class="flex gap-3">
                                    <span class="mt-1 size-1.5 shrink-0 rounded-full bg-brand-300"></span>
                                    <span><strong class="text-white">5 GB</strong> of storage</span>
                                </li>
                                <li class="flex gap-3">
                                    <span class="mt-1 size-1.5 shrink-0 rounded-full bg-brand-300"></span>
                                    <span>Core employee management features</span>
                                </li>
                            </ul>
                            <a href="{{ route('register') }}" class="mt-8 inline-flex w-full items-center justify-center rounded-lg border border-brand-500 bg-brand-800/60 px-5 py-3 text-sm font-semibold text-white transition hover:bg-brand-700">
                                Start for free
                            </a>
                        </article>

                        <article class="relative flex flex-col rounded-2xl border border-ember-500/70 bg-brand-900/70 p-7 shadow-2xl shadow-ember-950/20">
                            <div class="absolute right-5 top-5 rounded-full bg-ember-600 px-3 py-1 text-xs font-semibold text-white">Most flexible</div>
                            <p class="text-sm font-semibold uppercase tracking-wider text-ember-400">Business</p>
                            <div class="mt-5 flex items-end gap-2">
                                <span class="text-5xl font-semibold tracking-tight">500</span>
                                <div class="pb-1 text-sm text-brand-300">
                                    <span class="block font-semibold text-white">BDT / user</span>
                                    <span>per month</span>
                                </div>
                            </div>
                            <p class="mt-3 min-h-12 text-sm leading-6 text-brand-300">For growing teams with up to 100 employees.</p>

                            <div class="my-7 h-px bg-white/10"></div>
                            <ul class="flex flex-1 flex-col gap-4 text-sm text-brand-100">
                                <li class="flex gap-3">
                                    <span class="mt-1 size-1.5 shrink-0 rounded-full bg-ember-400"></span>
                                    <span><strong class="text-white">Up to 100 employees</strong></span>
                                </li>
                                <li class="flex gap-3">
                                    <span class="mt-1 size-1.5 shrink-0 rounded-full bg-ember-400"></span>
                                    <span><strong class="text-white">50 GB</strong> of storage</span>
                                </li>
                                <li class="flex gap-3">
                                    <span class="mt-1 size-1.5 shrink-0 rounded-full bg-ember-400"></span>
                                    <span>Additional storage at <strong class="text-white">100 BDT per GB</strong></span>
                                </li>
                                <li class="flex gap-3">
                                    <span class="mt-1 size-1.5 shrink-0 rounded-full bg-ember-400"></span>
                                    <span>Full HR, payroll, attendance, and workflow access</span>
                                </li>
                            </ul>
                            <a href="{{ route('register') }}" class="mt-8 inline-flex w-full items-center justify-center rounded-lg bg-ember-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-ember-950/20 transition hover:-translate-y-0.5 hover:bg-ember-500">
                                Choose Business
                            </a>
                        </article>

                        <article class="flex flex-col rounded-2xl border border-brand-700/70 bg-brand-900/45 p-7">
                            <p class="text-sm font-semibold uppercase tracking-wider text-brand-300">Enterprise</p>
                            <div class="mt-5">
                                <span class="text-4xl font-semibold tracking-tight">Custom solution</span>
                            </div>
                            <p class="mt-4 min-h-12 text-sm leading-6 text-brand-300">A tailored platform for your organization’s needs.</p>

                            <div class="my-7 h-px bg-white/10"></div>
                            <ul class="flex flex-1 flex-col gap-4 text-sm text-brand-100">
                                <li class="flex gap-3">
                                    <span class="mt-1 size-1.5 shrink-0 rounded-full bg-emerald-400"></span>
                                    <span>Custom features and integrations</span>
                                </li>
                                <li class="flex gap-3">
                                    <span class="mt-1 size-1.5 shrink-0 rounded-full bg-emerald-400"></span>
                                    <span>Flexible storage and user capacity</span>
                                </li>
                                <li class="flex gap-3">
                                    <span class="mt-1 size-1.5 shrink-0 rounded-full bg-emerald-400"></span>
                                    <span>Implementation and onboarding support</span>
                                </li>
                            </ul>
                            <a href="mailto:sales@erp2all.com?subject=Custom%20ERP2ALL%20solution" class="mt-8 inline-flex w-full items-center justify-center rounded-lg border border-brand-500 bg-brand-800/60 px-5 py-3 text-sm font-semibold text-white transition hover:bg-brand-700">
                                Contact sales
                            </a>
                        </article>
                    </div>
                </section>
            </main>

            <footer class="border-t border-white/10">
                <div class="mx-auto flex max-w-7xl flex-col gap-4 px-6 py-8 text-sm text-brand-400 sm:flex-row sm:items-center sm:justify-between lg:px-8">
                    <p>&copy; {{ date('Y') }} {{ config('app.name', 'ERP2ALL') }}</p>
                    <div class="flex items-center gap-5">
                        <a href="{{ route('home') }}" class="transition hover:text-white">Home</a>
                        <a href="{{ route('pricing') }}" class="transition hover:text-white">Pricing</a>
                        <a href="{{ route('login') }}" class="transition hover:text-white">Log in</a>
                    </div>
                </div>
            </footer>
        </div>
        @fluxScripts
    </body>
</html>
