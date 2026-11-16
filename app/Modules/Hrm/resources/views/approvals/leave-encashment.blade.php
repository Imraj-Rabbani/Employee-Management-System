{{-- A leave encashment for the approver (ShowsApprovalDetails). $document = LeaveEncashment. --}}
@php
    /** @var \App\Modules\Hrm\Models\LeaveEncashment $document */
    $num = fn ($v) => \App\Modules\Hrm\Models\LeaveApplication::daysLabel($v);
    $balance = $document->employee && $document->leaveType
        ? app(\App\Modules\Hrm\Services\LeaveLedger::class)->balance($document->employee, $document->leaveType, \App\Modules\Hrm\Data\LeaveYear::of($document->leave_year))
        : null;
@endphp
<dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ([
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
