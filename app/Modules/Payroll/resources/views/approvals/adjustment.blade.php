{{--
    A payroll adjustment for the approver (ShowsApprovalDetails): approval modal and approve-from-email page.
    $document = PayrollAdjustment.
--}}
@php /** @var \App\Modules\Payroll\Models\PayrollAdjustment $document */ @endphp
<dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ([
            __('Adjustment') => $document->number(),
            __('Employee') => ($document->employee?->code ?? '').' · '.($document->employee?->name ?? '?'),
            __('Month') => $document->period()->label(),
            __('Head') => $document->head ? $document->head->name.' ('.$document->head->type->label().')' : null,
            __('Amount') => $document->signedAmount(),
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
