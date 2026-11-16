{{--
    A leave application for the approver (ShowsApprovalDetails): approval modal and approve-from-email page.
    $document = LeaveApplication. Shows the balance before and after, so the approver sees what it costs.
--}}
@php
    /** @var \App\Modules\Hrm\Models\LeaveApplication $document */
    $num = fn ($v) => \App\Modules\Hrm\Models\LeaveApplication::daysLabel($v);
    $balance = $document->employee && $document->leaveType
        ? app(\App\Modules\Hrm\Services\LeaveLedger::class)->balance($document->employee, $document->leaveType, \App\Modules\Hrm\Data\LeaveYear::of($document->leave_year))
        : null;
@endphp
<dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ([
            __('Application') => $document->number(),
            __('Employee') => ($document->employee?->code ?? '').' · '.($document->employee?->name ?? '?'),
            __('Leave type') => $document->leaveType?->optionLabel(),
            __('Dates') => $document->datesLabel(),
            __('Days counted') => $num($document->days).' · '.$document->leaveDays->map(fn ($d) => $d->date->translatedFormat('D j M').($d->days < 1 ? ' (½)' : ''))->implode(', '),
            __('Balance') => $balance === null || ! $document->leaveType?->requires_balance ? null
                : ($document->approved_at
                    ? __(':n left', ['n' => $num($balance)])
                    : __(':now now, :after after approval', ['now' => $num($balance), 'after' => $num($balance - $document->days)])),
            __('Delegatee') => $document->delegatee?->name,
            __('Contact during leave') => $document->contact_during_leave,
            __('Reason') => $document->reason,
            __('Attachment') => $document->attachment_path
                ? new \Illuminate\Support\HtmlString('<a class=\'underline\' href=\''.e(route('hrm.leave.attachment', $document->id)).'\'>'.e($document->attachment_name).'</a>')
                : null,
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
