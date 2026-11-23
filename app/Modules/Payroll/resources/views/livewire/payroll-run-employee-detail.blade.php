{{-- One employee of a run in full. PHP side: App\Modules\Payroll\Livewire\PayrollRunEmployeeDetail --}}
@php
    use App\Modules\Payroll\Services\PayrollReview;
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $dt = 'text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400';
@endphp
<div>
    <x-ui.modal name="payroll-run-employee" size="xl"
        :heading="$row ? ($row->employee->code ?? '?').' · '.($row->employee->name ?? '') : __('Employee')"
        :subheading="$row ? $row->run->code.' · '.$row->run->title() : null">
        @if ($row)
            <div class="space-y-6">
                @if ($canRecalculate)
                    <div class="flex justify-end">
                        <flux:button size="sm" icon="arrow-path" wire:click="recalculate" wire:loading.attr="disabled" wire:target="recalculate">{{ __('Calculate again') }}</flux:button>
                    </div>
                @endif
                <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-3 lg:grid-cols-6">
                    <div><dt class="{{ $dt }}">{{ __('Entity') }}</dt><dd class="mt-1 text-sm">{{ $row->entity?->label() ?? '—' }}</dd></div>
                    <div><dt class="{{ $dt }}">{{ __('Paid days') }}</dt><dd class="mt-1 text-sm tabular-nums">{{ $num($row->payable_days) }} / {{ $num($row->period_days) }}</dd></div>
                    <div><dt class="{{ $dt }}">{{ __('LWP') }}</dt><dd class="mt-1 text-sm tabular-nums">{{ $num($row->lwp_days) }}</dd></div>
                    <div><dt class="{{ $dt }}">{{ __('Absent') }}</dt><dd class="mt-1 text-sm tabular-nums">{{ $num($row->absent_days) }}</dd></div>
                    <div><dt class="{{ $dt }}">{{ __('Overtime') }}</dt><dd class="mt-1 text-sm tabular-nums">{{ __(':h h', ['h' => $num($row->overtime_hours)]) }}</dd></div>
                    <div><dt class="{{ $dt }}">{{ __('Bank account') }}</dt><dd class="mt-1 text-sm">{{ $row->bankAccount ? $row->bankAccount->bank_name.' · '.$row->bankAccount->account_no : __('None') }}{{ $row->is_on_hold ? ' · '.__('salary on hold') : ($row->hold_bank_transfer ? ' · '.__('transfer on hold') : '') }}</dd></div>
                </dl>

                @if ($row->error)
                    <div class="rounded-xl border border-red-200 bg-red-50/70 p-3 text-sm text-red-800 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-200">{{ $row->error }}</div>
                @endif
                @if ($row->hasWarnings())
                    <ul class="list-disc space-y-1 rounded-xl border border-amber-200 bg-amber-50/70 py-3 ps-8 pe-3 text-sm text-amber-900 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-100">
                        @foreach ($row->warnings as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                @endif

                @if ($groups !== [])
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-zinc-200/70 text-left text-zinc-600 dark:border-white/10 dark:text-zinc-300">
                                    <th class="py-2 pe-3 font-medium">{{ __('Head') }}</th>
                                    <th class="px-3 py-2 font-medium">{{ __('From') }}</th>
                                    <th class="px-3 py-2 text-right font-medium" title="{{ __('What the full period pays') }}">{{ __('Full period') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Back pay') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Paid') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ $previous ? $previous->run->code : __('Previous') }}</th>
                                </tr>
                            </thead>
                            @foreach ($groups as $group)
                                <tbody>
                                    <tr><td colspan="6" class="pt-4 pb-1 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $group['label'] }}</td></tr>
                                    @foreach ($group['items'] as $item)
                                        <tr class="border-b border-zinc-200/50 dark:border-white/5" wire:key="detail-item-{{ $item->id }}">
                                            <td class="py-2 pe-3">{{ $item->head->name }} <span class="text-xs text-zinc-400">{{ $item->head->code }}</span></td>
                                            <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">{{ $item->source->label() }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ $item->actual->isZero() ? '' : $item->actual->format() }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ $item->arrear->isZero() ? '' : $item->arrear->format() }}</td>
                                            <td class="px-3 py-2 text-right font-medium tabular-nums">{{ $item->amount->format() }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums text-zinc-500 dark:text-zinc-400">{{ isset($before[$item->salary_head_id]) ? $before[$item->salary_head_id]->format() : '—' }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="font-medium">
                                        <td colspan="4" class="py-2 pe-3 text-right">{{ __('Total') }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $group['total']->format() }}</td>
                                        <td></td>
                                    </tr>
                                </tbody>
                            @endforeach
                        </table>
                    </div>

                    <div class="flex flex-wrap justify-end gap-x-8 gap-y-2 rounded-xl bg-brand-50/70 px-4 py-3 text-sm dark:bg-white/5">
                        <span>{{ __('Gross') }} <span class="font-semibold tabular-nums">{{ $row->gross->format() }}</span></span>
                        <span>{{ __('Deductions') }} <span class="font-semibold tabular-nums">{{ $row->deductions->format() }}</span></span>
                        <span>{{ __('Net pay') }} <span class="font-semibold tabular-nums">{{ $row->net->format() }}</span></span>
                        <span class="text-zinc-500 dark:text-zinc-400">
                            {{ $previous ? __('Previous :net (:percent)', ['net' => $previous->net->format(), 'percent' => PayrollReview::percentLabel($change['percent'])]) : __('No previous period') }}
                        </span>
                    </div>
                @endif

                @if ($arrears->isNotEmpty())
                    <div>
                        <flux:heading size="sm">{{ __('Back pay for earlier periods') }}</flux:heading>
                        <table class="mt-2 min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-zinc-200/70 text-left text-zinc-600 dark:border-white/10 dark:text-zinc-300">
                                    <th class="py-2 pe-3 font-medium">{{ __('Period') }}</th>
                                    <th class="px-3 py-2 font-medium">{{ __('Head') }}</th>
                                    <th class="px-3 py-2 font-medium">{{ __('Why') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Due now') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Paid before') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Back pay') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($arrears as $arrear)
                                    <tr class="border-b border-zinc-200/50 dark:border-white/5" wire:key="detail-arrear-{{ $arrear->id }}">
                                        <td class="py-2 pe-3 whitespace-nowrap">{{ $arrear->forRun->code ?? '' }} · {{ format_date($arrear->for_period_start) }} – {{ format_date($arrear->for_period_end) }}</td>
                                        <td class="px-3 py-2">{{ $arrear->head->name }}</td>
                                        <td class="px-3 py-2">{{ $arrear->reason->label() }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $arrear->due->format() }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $arrear->paid_before->format() }}</td>
                                        <td class="px-3 py-2 text-right font-medium tabular-nums">{{ $arrear->amount->format() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($adjustments->isNotEmpty())
                    <div>
                        <flux:heading size="sm">{{ __('Adjustments paid') }}</flux:heading>
                        <ul class="mt-2 space-y-1 text-sm">
                            @foreach ($adjustments as $adjustment)
                                <li wire:key="detail-adjustment-{{ $adjustment->id }}">{{ $adjustment->number() }} · {{ $adjustment->head->name ?? '' }} <span class="tabular-nums">{{ $adjustment->signedAmount() }}</span> · {{ $adjustment->reason }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif
    </x-ui.modal>
</div>
