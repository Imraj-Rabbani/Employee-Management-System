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
<x-ui.details :items="[
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
]" />
