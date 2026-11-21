<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head', ['title' => config('app.name', 'ERP2ALL')])
    </head>
    <body class="min-h-screen bg-brand-950 text-white antialiased">
        <div class="relative isolate overflow-hidden">
            <div class="absolute inset-0 -z-20 bg-[radial-gradient(circle_at_top_right,_rgba(191,59,43,0.18),transparent_32rem),radial-gradient(circle_at_bottom_left,_rgba(77,104,132,0.24),transparent_34rem)]"></div>
            <div class="absolute inset-0 -z-10 opacity-[0.07]" style="background-image: linear-gradient(rgba(255,255,255,.25) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.25) 1px, transparent 1px); background-size: 48px 48px;"></div>

            <header class="mx-auto flex max-w-7xl items-center justify-between px-6 py-6 lg:px-8">
                <a href="{{ route('home') }}" class="flex items-center" wire:navigate aria-label="{{ config('app.name') }} home">
                    <x-app-logo />
                </a>

                <nav class="flex items-center gap-3">
                    <a href="{{ route('login') }}" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-brand-100 transition hover:text-white">
                        Log in
                    </a>
                    <a href="{{ route('register') }}" class="rounded-lg bg-ember-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-ember-950/20 transition hover:bg-ember-500">
                        Register company
                    </a>
                </nav>
            </header>

            <main>
                <section class="mx-auto grid max-w-7xl items-center gap-12 px-6 pb-20 pt-16 lg:grid-cols-[1.08fr_.92fr] lg:px-8 lg:pb-28 lg:pt-24">
                    <div>
                        <p class="mb-5 inline-flex items-center gap-2 rounded-full border border-brand-600/60 bg-brand-900/70 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-brand-200">
                            <span class="size-2 rounded-full bg-ember-500"></span>
                            One workspace. Every team.
                        </p>
                        <h1 class="max-w-3xl text-5xl font-semibold tracking-tight text-white sm:text-6xl lg:text-7xl">
                            Run your company with clarity.
                        </h1>
                        <p class="mt-7 max-w-2xl text-lg leading-8 text-brand-200 sm:text-xl">
                            Manage employees, attendance, payroll, approvals, and company operations from one secure, connected platform.
                        </p>
                        <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                            <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-lg bg-ember-600 px-6 py-3.5 text-sm font-semibold text-white shadow-xl shadow-ember-950/20 transition hover:-translate-y-0.5 hover:bg-ember-500">
                                Get started
                            </a>
                            <a href="#features" class="inline-flex items-center justify-center rounded-lg border border-brand-600 bg-brand-900/40 px-6 py-3.5 text-sm font-semibold text-white transition hover:bg-brand-800">
                                Explore features
                                <svg class="ml-2 size-4" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 4v12m0 0 5-5m-5 5-5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                        </div>
                    </div>

                    <div class="relative mx-auto w-full max-w-xl">
                        <div class="absolute -inset-8 -z-10 rounded-full bg-brand-500/10 blur-3xl"></div>
                        <div class="rounded-2xl border border-white/10 bg-brand-900/75 p-3 shadow-2xl shadow-black/30 backdrop-blur-xl">
                            <div class="rounded-xl border border-white/10 bg-brand-950/70 p-5">
                                <div class="mb-6 flex items-center justify-between">
                                    <div>
                                        <p class="text-xs font-medium uppercase tracking-wider text-brand-400">Company overview</p>
                                        <p class="mt-1 text-lg font-semibold text-white">Operations</p>
                                    </div>
                                    <span class="rounded-full bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-300">All systems active</span>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="rounded-xl border border-brand-700/70 bg-brand-900/70 p-4">
                                        <p class="text-xs text-brand-400">Employees</p>
                                        <p class="mt-2 text-3xl font-semibold">HR</p>
                                        <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-brand-800"><div class="h-full w-4/5 rounded-full bg-brand-300"></div></div>
                                    </div>
                                    <div class="rounded-xl border border-brand-700/70 bg-brand-900/70 p-4">
                                        <p class="text-xs text-brand-400">Approvals</p>
                                        <p class="mt-2 text-3xl font-semibold">Flow</p>
                                        <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-brand-800"><div class="h-full w-3/5 rounded-full bg-ember-500"></div></div>
                                    </div>
                                </div>
                                <div class="mt-3 rounded-xl border border-brand-700/70 bg-brand-900/70 p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-xs text-brand-400">Monthly payroll</p>
                                            <p class="mt-1 text-sm font-semibold text-brand-100">Ready to process</p>
                                        </div>
                                        <span class="flex size-10 items-center justify-center rounded-full bg-emerald-400/10 text-emerald-300">
                                            <svg class="size-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 10 3 3 7-7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="features" class="border-t border-white/10 bg-brand-950/50">
                    <div class="mx-auto max-w-7xl px-6 py-20 lg:px-8 lg:py-24">
                        <div class="max-w-2xl">
                            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-ember-400">Platform features</p>
                            <h2 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Everything your operation needs</h2>
                            <p class="mt-4 text-brand-300">Purpose-built modules that work together across your organization.</p>
                        </div>

                        <div class="mt-12 grid gap-5 md:grid-cols-2">
                            <article class="group rounded-2xl border border-brand-700/70 bg-brand-900/45 p-6 transition hover:-translate-y-1 hover:border-brand-500/80">
                                <div class="flex size-11 items-center justify-center rounded-xl bg-brand-700/60 text-brand-100">
                                    <svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </div>
                                <h3 class="mt-5 text-xl font-semibold">Human Resources</h3>
                                <p class="mt-2 leading-7 text-brand-300">Maintain complete employee records, organization structures, recruitment, leave, balances, and employee self-service.</p>
                                <ul class="mt-5 grid gap-2 text-sm text-brand-200 sm:grid-cols-2">
                                    <li>• Employee profiles</li><li>• Organization chart</li><li>• Recruitment pipeline</li><li>• Leave management</li>
                                </ul>
                            </article>

                            <article class="group rounded-2xl border border-brand-700/70 bg-brand-900/45 p-6 transition hover:-translate-y-1 hover:border-brand-500/80">
                                <div class="flex size-11 items-center justify-center rounded-xl bg-ember-600/15 text-ember-400">
                                    <svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M3 10h18M7 15h4m2 0h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                                </div>
                                <h3 class="mt-5 text-xl font-semibold">Payroll & Attendance</h3>
                                <p class="mt-2 leading-7 text-brand-300">Plan shifts and rosters, process punches and overtime, and run payroll with controlled salary setups and approvals.</p>
                                <ul class="mt-5 grid gap-2 text-sm text-brand-200 sm:grid-cols-2">
                                    <li>• Rosters & punches</li><li>• Attendance processing</li><li>• Salary setup</li><li>• Payroll runs</li>
                                </ul>
                            </article>

                            <article class="group rounded-2xl border border-brand-700/70 bg-brand-900/45 p-6 transition hover:-translate-y-1 hover:border-brand-500/80">
                                <div class="flex size-11 items-center justify-center rounded-xl bg-brand-700/60 text-brand-100">
                                    <svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 3v18M19 3v18M5 7h14M5 17h14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><circle cx="12" cy="7" r="2" fill="currentColor"/><circle cx="12" cy="17" r="2" fill="currentColor"/></svg>
                                </div>
                                <h3 class="mt-5 text-xl font-semibold">Approvals & Workflow</h3>
                                <p class="mt-2 leading-7 text-brand-300">Keep decisions moving with a personal approval inbox and configurable workflows for company processes.</p>
                                <ul class="mt-5 grid gap-2 text-sm text-brand-200 sm:grid-cols-2">
                                    <li>• Approval inbox</li><li>• Workflow setup</li><li>• Request tracking</li><li>• Role-based routing</li>
                                </ul>
                            </article>

                            <article class="group rounded-2xl border border-brand-700/70 bg-brand-900/45 p-6 transition hover:-translate-y-1 hover:border-brand-500/80">
                                <div class="flex size-11 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-300">
                                    <svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06-1.91 1.91-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V20h-2.7v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06-1.91-1.91.06-.06A1.65 1.65 0 0 0 7.9 15a1.65 1.65 0 0 0-1.51-1H6.3v-2.7h.09A1.65 1.65 0 0 0 7.9 10a1.65 1.65 0 0 0-.33-1.82l-.06-.06L9.42 6.2l.06.06A1.65 1.65 0 0 0 11.3 6a1.65 1.65 0 0 0 1-1.51V4.4h2.7v.09A1.65 1.65 0 0 0 16 6a1.65 1.65 0 0 0 1.82.33l.06-.06 1.91 1.91-.06.06A1.65 1.65 0 0 0 19.4 10a1.65 1.65 0 0 0 1.51 1h.09v2.7h-.09A1.65 1.65 0 0 0 19.4 15Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </div>
                                <h3 class="mt-5 text-xl font-semibold">Company Administration</h3>
                                <p class="mt-2 leading-7 text-brand-300">Control users, roles, permissions, fiscal years, currencies, and tenant settings from one administrative workspace.</p>
                                <ul class="mt-5 grid gap-2 text-sm text-brand-200 sm:grid-cols-2">
                                    <li>• Users & roles</li><li>• Permissions</li><li>• Fiscal years</li><li>• Company settings</li>
                                </ul>
                            </article>
                        </div>
                    </div>
                </section>
            </main>

            <footer class="border-t border-white/10">
                <div class="mx-auto flex max-w-7xl flex-col gap-4 px-6 py-8 text-sm text-brand-400 sm:flex-row sm:items-center sm:justify-between lg:px-8">
                    <p>&copy; {{ date('Y') }} {{ config('app.name', 'ERP2ALL') }}</p>
                    <div class="flex items-center gap-5">
                        <a href="{{ route('login') }}" class="transition hover:text-white">Log in</a>
                        <a href="{{ route('register') }}" class="transition hover:text-white">Register company</a>
                    </div>
                </div>
            </footer>
        </div>
        @fluxScripts
    </body>
</html>
