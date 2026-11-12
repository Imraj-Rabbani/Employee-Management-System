{{-- A leave encashment for the approver (ShowsApprovalDetails). $document = LeaveEncashment. --}}
@php
    /** @var \App\Modules\Hrm\Models\LeaveEncashment $document */
    $num = fn ($v) => \App\Modules\Hrm\Models\LeaveApplication::daysLabel($v);
    $balance = $document->employee && $document->leaveType
        ? app(\App\Modules\Hrm\Services\LeaveLedger::class)->balance($document->employee, $document->leaveType, \App\Modules\Hrm\Data\LeaveYear::of($document->leave_year))
        : null;
@endphp
<x-ui.details :items="[
    __('Encashment') => $document->number(),
    __('Employee') => ($document->employee?->code ?? '').' · '.($document->employee?->name ?? '?'),
    __('Leave type') => $document->leaveType?->optionLabel(),
    __('Days') => $num($document->days),
    __('Balance') => $balance === null ? null
        : ($document->approved_at
            ? __(':n left', ['n' => $num($balance)])
            : __(':now now, :after after approval', ['now' => $num($balance), 'after' => $num($balance - $document->days)])),
    __('Amount') => __('Worked out by payroll'),
    __('Reason') => $document->reason,
]" />
