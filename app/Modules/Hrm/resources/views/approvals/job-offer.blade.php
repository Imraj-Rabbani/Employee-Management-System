{{--
    A job offer's details and salary comparison. $document = JobOffer.
    Used twice: shown to the approver (approval modal + email page, via ShowsApprovalDetails) and on the
    offer's own page (OfferDetails), so both always show the same.
--}}
@php
    /** @var \App\Modules\Hrm\Models\JobOffer $document */
    $application = $document->application;
    $verdicts = [
        'below' => ['color' => 'warning', 'label' => __('Below')],
        'inside' => ['color' => 'success', 'label' => __('Within')],
        'above' => ['color' => 'danger', 'label' => __('Above')],
    ];
@endphp
<div class="space-y-4">
    <x-ui.details :items="[
        __('Applicant') => $application->applicant->name,
        __('Job') => $application->jobPosting->number().' · '.$application->jobPosting->title,
        __('Org unit') => $application->jobPosting->manpowerRequest->orgUnit?->name,
        __('Designation') => $document->designation?->name,
        __('Grade') => $document->grade?->name,
        __('Employment type') => $document->employmentType?->name,
        __('Gross salary (monthly)') => number_format((float) $document->gross_salary),
        __('Joining date') => format_date($document->joining_date),
        __('Answer by') => $document->expires_on ? format_date($document->expires_on) : null,
    ]" />

    <div class="overflow-hidden rounded-xl ring-1 ring-zinc-500/15">
        <table class="w-full text-sm">
            <thead class="bg-zinc-500/5 text-left text-xs uppercase tracking-wide text-zinc-500">
                <tr>
                    <th class="px-4 py-2 font-medium">{{ __('Compared with') }}</th>
                    <th class="px-4 py-2 text-right font-medium">{{ __('Amount') }}</th>
                    <th class="px-4 py-2 font-medium">{{ __('Offer is') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($document->comparisons() as $row)
                    <tr class="border-t border-zinc-500/10">
                        <td class="px-4 py-2">{{ $row['label'] }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $row['range'] ?? '—' }}</td>
                        <td class="px-4 py-2">
                            @if ($row['verdict'])
                                <x-ui.badge :color="$verdicts[$row['verdict']]['color']">{{ $verdicts[$row['verdict']]['label'] }}</x-ui.badge>
                            @else
                                <span class="text-zinc-400">{{ __('Not set') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($document->terms)
        <div>
            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Terms') }}</div>
            <flux:text class="mt-1 whitespace-pre-line">{{ $document->terms }}</flux:text>
        </div>
    @endif
</div>
