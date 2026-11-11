{{--
    An attendance correction for the approver (ShowsApprovalDetails): approval modal and approve-from-email page.
    $document = AttendanceCorrection. Shows what was processed and punched next to what is asked.
--}}
@php
    /** @var \App\Modules\Payroll\Models\AttendanceCorrection $document */
    $day = $document->attendanceDay();
    $punches = \App\Modules\Payroll\Models\PunchLog::query()
        ->where('employee_id', $document->employee_id)
        ->whereBetween('punched_at', [$document->date->copy()->startOfDay(), $document->date->copy()->addDay()->endOfDay()])
        ->orderBy('punched_at')->pluck('punched_at');
    $time = fn ($at) => \App\Modules\Payroll\Models\AttendanceCorrection::timeLabel($at, $document->date);
@endphp
<x-ui.details :items="[
    __('Correction') => $document->number(),
    __('Employee') => ($document->employee?->code ?? '').' · '.($document->employee?->name ?? '?'),
    __('Day') => format_date($document->date).' · '.$document->date->translatedFormat('l'),
    __('Asked: in') => $time($document->requested_in_at).($document->requested_in_at ? '' : ' ('.__('keep the punch').')'),
    __('Asked: out') => $time($document->requested_out_at).($document->requested_out_at ? '' : ' ('.__('keep the punch').')'),
    __('Reason') => $document->reason,
    __('Processed now') => $day ? $day->status->label().', '.__('in').' '.$time($day->in_at).', '.__('out').' '.$time($day->out_at) : __('Not processed yet'),
    __('Punches (that day and the next)') => $punches->map(fn ($p) => $time($p))->join(', ') ?: __('None'),
]" />
