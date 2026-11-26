@php
    use App\Modules\Hrm\Enums\JobPostingStatus;
    $request = $posting->manpowerRequest;
@endphp
<div class="space-y-4">
    <div class="glass overflow-hidden rounded-2xl">
        <div class="p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:heading size="xl">{{ $posting->title }}</flux:heading>
                        <x-ui.badge :color="$posting->status->color()">{{ $posting->status->label() }}</x-ui.badge>
                    </div>
                    <flux:text class="mt-1">
                        {{ $posting->number() }} · {{ $request->entity?->name }} ·
                        {{ trans_choice(':count opening|:count openings', $posting->openings, ['count' => $posting->openings]) }}
                    </flux:text>
                    <flux:text class="mt-1 text-xs">{{ $posting->applicationWindow() }}</flux:text>
                </div>

                <div class="flex flex-wrap gap-2">
                    @if ($posting->status !== JobPostingStatus::Closed)
                        <flux:button icon="pencil-square" x-on:click="$dispatch('edit-job-posting', { id: {{ $posting->id }} })">{{ __('Edit') }}</flux:button>
                    @endif

                    @if ($posting->status === JobPostingStatus::Open)
                        <flux:button icon="lock-closed" x-on:click="$dispatch('confirm', {
                            title: {{ Js::from(__('Close this posting?')) }},
                            message: {{ Js::from(__('No new applicants can be added. Applicants already in the pipeline carry on.')) }},
                            confirmText: {{ Js::from(__('Close')) }},
                            onConfirm: () => $wire.close(),
                        })">{{ __('Close') }}</flux:button>
                    @else
                        <flux:button variant="primary" icon="megaphone" wire:click="open">
                            {{ $posting->status === JobPostingStatus::Closed ? __('Reopen') : __('Open for applications') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="grid items-start gap-4 lg:grid-cols-3">
        {{-- Collapsible cards (the arrow; remembered in this browser). --}}
        <div class="glass overflow-hidden rounded-2xl lg:col-span-2" x-data="{ open: $persist(true).as('posting-card-details') }">
            <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" class="flex w-full items-center gap-2 px-6 py-4 text-left" x-bind:class="open && 'border-b border-white/50 dark:border-white/10'">
                <flux:icon.chevron-down class="size-5 shrink-0 text-zinc-500 transition" x-bind:class="open || '-rotate-90'" />
                <flux:heading size="lg">{{ __('Details') }}</flux:heading>
            </button>
            <div class="space-y-6 p-6" x-show="open" x-collapse>
                <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                            __('Opens') => format_date($posting->open_date),
                            __('Closes') => $posting->close_date ? format_date($posting->close_date) : __('Until closed'),
                            __('Openings') => $posting->openings,
                    ] as $detailLabel => $detailValue)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $detailLabel }}</dt>
                            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                @if ($detailValue === null || $detailValue === '')
                                    <span class="text-zinc-400">—</span>
                                @else
                                    {{ $detailValue }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>

                @if ($posting->form_url || $posting->responses_url)
                    <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
                        @if ($posting->form_url)
                            <a href="{{ $posting->form_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-brand-800 dark:text-brand-200 underline decoration-current/50 hover:decoration-current underline-offset-4 hover:text-blue-600 dark:hover:text-blue-400"><flux:icon.clipboard-document-list variant="micro" /> {{ __('Application form') }}</a>
                        @endif
                        @if ($posting->responses_url)
                            <a href="{{ $posting->responses_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-brand-800 dark:text-brand-200 underline decoration-current/50 hover:decoration-current underline-offset-4 hover:text-blue-600 dark:hover:text-blue-400"><flux:icon.table-cells variant="micro" /> {{ __('Responses sheet') }}</a>
                        @endif
                    </div>
                @endif

                <div>
                    <flux:heading class="mb-1">{{ __('Description') }}</flux:heading>
                    <flux:text class="whitespace-pre-line">{{ $posting->description ?: '—' }}</flux:text>
                </div>
                <div>
                    <flux:heading class="mb-1">{{ __('Requirements') }}</flux:heading>
                    <flux:text class="whitespace-pre-line">{{ $posting->requirements ?: '—' }}</flux:text>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="glass overflow-hidden rounded-2xl" x-data="{ open: $persist(true).as('posting-card-request') }">
                <div class="flex items-center justify-between gap-2 px-6 py-4" x-bind:class="open && 'border-b border-white/50 dark:border-white/10'">
                    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" class="flex items-center gap-2 text-left">
                        <flux:icon.chevron-down class="size-5 shrink-0 text-zinc-500 transition" x-bind:class="open || '-rotate-90'" />
                        <flux:heading size="lg">{{ __('Manpower request') }}</flux:heading>
                    </button>
                    <flux:link :href="route('hrm.recruitment.requests.show', $request->id)" wire:navigate class="text-sm">{{ $request->number() }}</flux:link>
                </div>
                <div class="space-y-6 p-6" x-show="open" x-collapse>
                    <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3 !grid-cols-1">
                        @foreach ([
                                __('For') => $request->headcount.' × '.($request->designation?->name ?? '?'),
                                __('Grade') => $request->grade?->name,
                                __('Salary (monthly gross)') => $request->salaryRange(),
                                __('Still to advertise') => $request->openingsToAdvertise(),
                        ] as $detailLabel => $detailValue)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $detailLabel }}</dt>
                                <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                    @if ($detailValue === null || $detailValue === '')
                                        <span class="text-zinc-400">—</span>
                                    @else
                                        {{ $detailValue }}
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
