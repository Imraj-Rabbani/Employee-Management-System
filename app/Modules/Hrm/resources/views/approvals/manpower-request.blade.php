{{--
    A manpower request's details. $document = ManpowerRequest.
    Used twice: shown to the approver (approval modal + email page, via ShowsApprovalDetails)
    and on the request page itself (hrm::recruitment.request-show), so both always show the same.
--}}
@php /** @var \App\Modules\Hrm\Models\ManpowerRequest $document */ @endphp
<div class="space-y-4">
    <x-ui.details :items="[
        __('Org unit') => $document->orgUnit?->name,
        __('Designation') => $document->designation?->name,
        __('Position') => $document->position?->name,
        __('Headcount') => $document->headcount,
        __('Type') => $document->type->label(),
        __('Replaces') => $document->replacesEmployee ? $document->replacesEmployee->code.' · '.$document->replacesEmployee->name : null,
        __('Grade') => $document->grade?->name,
        __('Employment type') => $document->employmentType?->name,
        __('Salary (monthly gross)') => $document->salaryRange(),
        __('Needed by') => $document->needed_by ? format_date($document->needed_by) : null,
    ]" />

    @if ($document->justification)
        <div>
            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Justification') }}</div>
            <flux:text class="mt-1 whitespace-pre-line">{{ $document->justification }}</flux:text>
        </div>
    @endif
</div>
