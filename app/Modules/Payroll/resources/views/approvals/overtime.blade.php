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
<x-ui.details :items="[
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
]" />
