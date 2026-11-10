{{--
    A roster for the approver (ShowsApprovalDetails): approval modal and approve-from-email page.
    $document = Roster. A read-only grid with codes; empty cells are "not planned".
--}}
@php
    /** @var \App\Modules\Payroll\Models\Roster $document */
    $employees = $document->gridEmployees();
    $matrix = $document->cellMatrix($employees);
    $codes = \App\Modules\Payroll\Models\Shift::query()->pluck('code', 'id');
    $label = fn (string $v) => match ($v) { 'off' => __('Off'), 'call' => __('OC'), '' => '', default => $codes[(int) $v] ?? '?' };
    $plannedRows = array_filter($matrix, fn ($row) => array_filter($row, fn ($v) => $v !== '') !== []);
@endphp
<div class="space-y-4">
    <x-ui.details :items="[
        __('Roster') => $document->number().' · '.$document->title,
        __('Org unit') => $document->orgUnit?->path(),
        __('Period') => format_date($document->from_date).' – '.format_date($document->to_date),
        __('Change of') => $document->replaces?->number(),
        __('Employees planned') => count($plannedRows),
        __('Note') => $document->note,
    ]" />

    @if ($plannedRows)
        <div class="max-h-96 overflow-auto rounded-lg border border-zinc-200 dark:border-white/10">
            <table class="text-[11px]">
                <thead class="sticky top-0 bg-white dark:bg-zinc-900">
                    <tr>
                        <th class="sticky left-0 bg-white px-2 py-1 text-left dark:bg-zinc-900">{{ __('Employee') }}</th>
                        @foreach ($document->dates() as $date)
                            <th class="px-1 py-1 text-center font-medium {{ $date->isWeekend() ? 'text-ember-700 dark:text-ember-300' : '' }}">{{ $date->day }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($employees as $employee)
                        @continue(! isset($plannedRows[$employee->id]))
                        <tr class="border-t border-zinc-100 dark:border-white/5">
                            <th class="sticky left-0 whitespace-nowrap bg-white px-2 py-0.5 text-left font-normal dark:bg-zinc-900">{{ $employee->code }} · {{ $employee->name }}</th>
                            @foreach ($matrix[$employee->id] as $value)
                                <td class="px-1 py-0.5 text-center {{ $value === 'off' ? 'text-zinc-400' : '' }}">{{ $label($value) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
