{{--
    Payroll → Attendance → one roster. The route passes $roster (already checked with visibleTo).
    Header (Edit opens RosterForm), the grid with its actions (RosterGrid), and the approval timeline.
--}}
@php [$statusLabel, $statusColor] = $roster->statusBadge(); @endphp

<x-layouts.app :title="$roster->number()">
    <div class="space-y-4">
        <x-ui.section-tabs section="payroll.attendance" />

        <flux:link :href="route('payroll.attendance.rosters')" wire:navigate class="inline-flex items-center gap-1 text-sm">
            <flux:icon.arrow-left variant="micro" /> {{ __('All rosters') }}
        </flux:link>

        <div class="glass overflow-hidden rounded-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
                <div>
                    <flux:heading size="lg">{{ $roster->number().' · '.$roster->title }}</flux:heading>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <x-ui.badge :color="$statusColor">{{ $statusLabel }}</x-ui.badge>
                    @if ($roster->isEditable())
                        <flux:button size="sm" icon="pencil-square" x-on:click="$dispatch('edit-roster', { id: {{ $roster->id }} })">{{ __('Edit') }}</flux:button>
                    @endif
                </div>
            </div>

            <div class="p-6">
                <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Entity') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $roster->entity?->label() ?? '—' }}</dd>
                    </div>

                    {{-- Only for a change request: link to the roster it changes. --}}
                    @if ($roster->replaces)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Change of') }}</dt>
                            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                <a href="{{ route('payroll.attendance.rosters.show', $roster->replaces->id) }}" wire:navigate class="font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">
                                    {{ $roster->replaces->number() }} · {{ $roster->replaces->title }}
                                </a>
                            </dd>
                        </div>
                    @endif

                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Period') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ format_date($roster->from_date) }} – {{ format_date($roster->to_date) }} ({{ trans_choice(':count day|:count days', count($roster->dates())) }})</dd>
                    </div>

                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Note') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $roster->note ?: '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <livewire:payroll::attendance.roster-grid :roster-id="$roster->id" />

        @if ($roster->approvalStatus())
            <div class="glass overflow-hidden rounded-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
                    <div>
                        <flux:heading size="lg">{{ __('Approval') }}</flux:heading>
                    </div>
                </div>

                <div class="p-6">
                    <x-approval-timeline :document="$roster" />
                </div>
            </div>
        @endif

        <livewire:payroll::attendance.roster-form />
    </div>
</x-layouts.app>
