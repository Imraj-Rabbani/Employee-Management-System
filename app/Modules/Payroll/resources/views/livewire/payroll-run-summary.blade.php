{{-- The run page's header. PHP side: App\Modules\Payroll\Livewire\PayrollRunSummary --}}
@php
    use App\Modules\Payroll\Services\PayrollReview;
    $tile = 'rounded-xl border border-white/50 bg-white/40 px-4 py-3 dark:border-white/10 dark:bg-white/5';
    $changeText = fn (array $c) => $previous ? ($c['diff']->isNegative() ? '' : '+').$c['diff']->format().' · '.PayrollReview::percentLabel($c['percent']) : null;
@endphp
<div class="glass overflow-hidden rounded-2xl" @if ($busy) wire:poll.3s @endif>
    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
        <div>
            <flux:heading size="lg">{{ $run->code }} · {{ $run->title() }}</flux:heading>
            <flux:text class="mt-1">
                {{ $run->run_type->label() }}@if ($run->head) · {{ $run->head->name }}@endif
                @if ($run->amount_basis) · {{ $run->amount_basis->label() }}@endif
                @if ($run->eligibilitySummary()) · {{ $run->eligibilitySummary() }}@endif
                · {{ $run->entity?->label() ?? __('Whole company') }}
                · {{ __('Pay date :date', ['date' => format_date($run->pay_date) ?: '—']) }}
                @if ($run->processed_at) · {{ __('Calculated :when', ['when' => $run->processed_at->diffForHumans()]) }}@endif
            </flux:text>
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            <x-ui.badge :color="$run->status->color()">{{ $run->status->label() }}</x-ui.badge>
            @can('calculate', $run)
                <flux:button size="sm" icon="play" wire:click="calculate">{{ $run->status === \App\Modules\Payroll\Enums\PayrollRunStatus::Draft ? __('Calculate') : __('Calculate again') }}</flux:button>
            @endcan
            @can('update', $run)
                <flux:button size="sm" icon="pencil-square" x-on:click="$dispatch('edit-payroll-run', { id: {{ $run->id }} })">{{ __('Edit') }}</flux:button>
            @endcan
            @can('submit', $run)
                <flux:button size="sm" variant="primary" icon="paper-airplane" x-on:click="$dispatch('confirm', {
                    title: {{ Js::from(__('Submit :code for approval?', ['code' => $run->code])) }},
                    message: {{ Js::from(__('From now on its numbers cannot change, unless an approver sends it back or you take it back.')) }},
                    confirmText: {{ Js::from(__('Submit')) }},
                    onConfirm: () => $wire.submit(),
                })">{{ __('Submit for approval') }}</flux:button>
            @endcan
            @can('withdraw', $run)
                <flux:button size="sm" icon="arrow-uturn-left" x-on:click="$dispatch('confirm', {
                    title: {{ Js::from(__('Take :code back?', ['code' => $run->code])) }},
                    message: {{ Js::from(__('The approvers are no longer asked; the run can be changed and submitted again.')) }},
                    confirmText: {{ Js::from(__('Take back')) }},
                    onConfirm: () => $wire.withdraw(),
                })">{{ __('Take back') }}</flux:button>
            @endcan
            @can('lock', $run)
                <flux:button size="sm" variant="primary" icon="lock-closed" x-on:click="$dispatch('confirm', {
                    title: {{ Js::from(__('Lock :code?', ['code' => $run->code])) }},
                    message: {{ Js::from(__('Locking is final: the run and the attendance of its period can never change again. Later changes are paid as arrears.')) }},
                    confirmText: {{ Js::from(__('Lock')) }},
                    onConfirm: () => $wire.lock(),
                })">{{ __('Lock') }}</flux:button>
            @endcan
            <flux:button size="sm" icon="chart-bar" x-on:click="$dispatch('show-payroll-run', { id: {{ $run->id }} })">{{ __('Progress') }}</flux:button>
        </div>
    </div>

    <div class="grid gap-3 p-6 sm:grid-cols-2 lg:grid-cols-4">
        <div class="{{ $tile }}">
            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Employees') }}</div>
            <div class="mt-1 text-xl font-semibold tabular-nums">{{ number_format($run->employee_count) }}</div>
            <div class="mt-1 text-xs">
                @if ($failed > 0)<span class="font-medium text-red-600 dark:text-red-400">{{ __(':n failed', ['n' => $failed]) }}</span>@endif
                @if ($pending > 0)<span class="text-zinc-500 dark:text-zinc-400">{{ __(':n not calculated', ['n' => $pending]) }}</span>@endif
                @if ($failed + $pending === 0)<span class="text-emerald-700 dark:text-emerald-300">{{ __('All calculated') }}</span>@endif
            </div>
        </div>
        <div class="{{ $tile }}">
            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Gross') }}</div>
            <div class="mt-1 text-xl font-semibold tabular-nums">{{ $run->total_gross->format() }}</div>
            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $changeText($gross) ?? __('No previous period') }}</div>
        </div>
        <div class="{{ $tile }}">
            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Deductions') }}</div>
            <div class="mt-1 text-xl font-semibold tabular-nums">{{ $run->total_deductions->format() }}</div>
        </div>
        <div class="{{ $tile }}">
            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Net pay') }}</div>
            <div class="mt-1 text-xl font-semibold tabular-nums">{{ $run->total_net->format() }}</div>
            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                @if ($previous)
                    <span @class(['font-medium text-amber-700 dark:text-amber-300' => PayrollReview::isVariance($net['percent'])])>{{ $changeText($net) }}</span>
                    {{ __('vs :run', ['run' => $previous->code]) }}
                @else
                    {{ __('No previous period') }}
                @endif
            </div>
        </div>
    </div>

    @if ($run->note)
        <div class="border-t border-white/50 px-6 py-3 text-sm text-zinc-600 dark:border-white/10 dark:text-zinc-300">{{ $run->note }}</div>
    @endif

    @if ($run->approvalStatus() || $run->locked_at)
        <div class="border-t border-white/50 px-6 py-4 dark:border-white/10">
            <flux:heading size="sm" class="mb-3">{{ __('Approval') }}</flux:heading>
            <x-approval-timeline :document="$run" />
            @if ($run->locked_at)
                <flux:text class="mt-3 text-sm">{{ __('Locked :date by :name.', ['date' => format_date($run->locked_at), 'name' => \App\Models\User::find($run->locked_by_user_id)?->name ?? '?']) }}</flux:text>
            @endif
        </div>
    @endif
</div>
