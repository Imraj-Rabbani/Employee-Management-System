{{--
    An overtime request for the approver (ShowsApprovalDetails): approval modal and approve-from-email page.
    $document = OvertimeRequest. Shows the overtime the punches show next to what is asked and recommended.
--}}
@php
    /** @var \App\Modules\Payroll\Models\OvertimeRequest $document */
    $day = $document->attendanceDay();
    $h = fn ($v) => \App\Modules\Payroll\Models\OvertimeRequest::hoursLabel($v);
    $m = fn (int $min) => \App\Modules\Payroll\Models\AttendanceDay::hoursLabel($min);
@endphp
<dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ([
            __('Request') => $document->number(),
            __('Employee') => ($document->employee?->code ?? '').' · '.($document->employee?->name ?? '?'),
            __('Day') => format_date($document->date).' · '.$document->date->translatedFormat('l').($day ? ' · '.$day->day_type->label() : ''),
            __('Shift') => $document->shift?->code,
            __('Asked') => __(':hours h', ['hours' => $h($document->hours)]),
            __('Recommended so far') => $document->approved_at ? $h($document->recommended_hours) : $h($document->recommendedHours()),
            __('Approved') => $document->approved_at ? __(':hours h', ['hours' => $h($document->approved_hours)]) : null,
            __('Work done') => $document->detail,
            __('Worked (punches)') => $day
                ? __('in :in, out :out · overtime :ot (before shift :pre, after :post)', [
                    'in' => $day->in_at?->format('H:i') ?? '—', 'out' => $day->out_at?->format('H:i') ?? '—',
                    'ot' => $m($day->ot_minutes), 'pre' => $m($day->ot_pre_minutes), 'post' => $m($day->ot_post_minutes),
                ])
                : __('Not processed yet'),
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
