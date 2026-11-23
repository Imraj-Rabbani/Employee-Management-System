{{--
    A payroll run for the approver (ShowsApprovalDetails): approval modal and approve-from-email page.
    $document = PayrollRun. Totals, the change against the previous period, and what the review found.
--}}
@php
    /** @var \App\Modules\Payroll\Models\PayrollRun $document */
    $summary = $document->reviewSummary();
    $dt = 'text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400';
@endphp
<div class="space-y-4">
    <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
                __('Run') => $document->code.' · '.$document->title(),
                __('Scope') => $document->entity?->label() ?? __('Whole company'),
                __('Pay date') => format_date($document->pay_date),
                __('Employees') => number_format($document->employee_count),
                __('Gross') => $document->total_gross->format(),
                __('Deductions') => $document->total_deductions->format(),
                __('Net pay') => $document->total_net->format(),
                __('Previous period') => $summary['previous']
                    ? $summary['previous']->code.' · '.$summary['previous']->total_net->format().' ('.\App\Modules\Payroll\Services\PayrollReview::percentLabel($summary['net']['percent']).')'
                    : null,
                __('Note') => $document->note,
        ] as $detailLabel => $detailValue)
            <div>
                <dt class="{{ $dt }}">{{ $detailLabel }}</dt>
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

    <div>
        <div class="{{ $dt }}">{{ __('Review findings') }}</div>
        @if ($summary['exceptions'] === [])
            <p class="mt-1 text-sm text-emerald-700 dark:text-emerald-300">{{ __('Nothing to check.') }}</p>
        @else
            <ul class="mt-1 space-y-0.5 text-sm">
                @foreach ($summary['exceptions'] as $finding)
                    <li>{{ $finding['label'] }}: <span class="font-medium">{{ $finding['count'] }}</span></li>
                @endforeach
            </ul>
        @endif
    </div>

    @can('payroll.run.view')
        <flux:link :href="route('payroll.runs.show', $document->id)" class="text-sm">{{ __('Open the run (register, breakdowns)') }}</flux:link>
    @endcan
</div>
