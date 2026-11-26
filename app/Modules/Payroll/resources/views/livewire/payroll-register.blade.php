{{-- The payroll register of one run. PHP side: App\Modules\Payroll\Livewire\PayrollRegister --}}
@php
    use App\Modules\Payroll\Enums\HeadType;
    use App\Modules\Payroll\Services\PayrollReview;
    $activeFilters = count(array_filter([$search !== '', $entity !== '', $status !== '']));
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $sortable = function (string $field, string $label, bool $right = false) use ($sortBy, $sortDir) {
        return ['field' => $field, 'label' => $label, 'right' => $right, 'active' => $sortBy === $field || ($sortBy === '' && $field === 'code'), 'dir' => $sortBy === '' ? 'asc' : $sortDir];
    };
@endphp
<div class="glass overflow-hidden rounded-2xl">
    <div class="flex flex-wrap items-center gap-2 border-b border-white/50 p-4 dark:border-white/10">
        @if ($canRecalculate)
            {{-- The ticked employees, at once (no queue). The whole run: "Calculate again" in the header. --}}
            <flux:button size="sm" icon="arrow-path" x-bind:disabled="$wire.selected.length === 0" wire:click="recalculate" wire:loading.attr="disabled" wire:target="recalculate">{{ __('Calculate again') }}</flux:button>
        @endif
        <flux:button wire:click="export" size="sm" icon="arrow-down-tray">{{ __('Export') }}</flux:button>
        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ trans_choice(':count employee|:count employees', $totals['count']) }}</span>
        <span x-show="$wire.selected.length > 0" x-cloak class="text-xs text-zinc-500 dark:text-zinc-400" x-text="'· ' + $wire.selected.length + ' ' + {{ Js::from(__('selected')) }}"></span>

        <flux:spacer />

        <flux:modal.trigger name="register-filters">
            <flux:button size="sm" icon="funnel">{{ __('Filter') }}{{ $activeFilters > 0 ? " ($activeFilters)" : '' }}</flux:button>
        </flux:modal.trigger>
    </div>

    <x-ui.modal name="register-filters" :heading="__('Filter')">
        <flux:field>
            <flux:label>{{ __('Search') }}</flux:label>
            <flux:input wire:model="search" icon="magnifying-glass" :placeholder="__('Code or name…')" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Entity') }}</flux:label>
            <flux:select wire:model="entity">
                <flux:select.option value="">{{ __('All') }}</flux:select.option>
                @foreach ($entities as $optionValue => $optionLabel)
                    <flux:select.option value="{{ $optionValue }}">{{ $optionLabel }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Show') }}</flux:label>
            <flux:select wire:model="status">
                <flux:select.option value="">{{ __('Everyone') }}</flux:select.option>
                @foreach ($statusOptions as $optionValue => $optionLabel)
                    <flux:select.option value="{{ $optionValue }}">{{ $optionLabel }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        <x-slot:footer>
            <flux:button wire:click="clearFilters" variant="ghost">{{ __('Clear') }}</flux:button>
            <flux:button wire:click="applyFilters" variant="primary">{{ __('Apply') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>

    <div class="overflow-x-auto" wire:loading.class="opacity-60">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-white/50 text-left text-zinc-600 dark:border-white/10 dark:text-zinc-300">
                    <th class="w-10 px-3 py-3" x-data="{ ids: {{ Js::from($rows->map(fn ($row) => (string) $row->getKey())->values()) }} }">
                        <input type="checkbox" class="size-4 rounded border-zinc-300 text-brand-800 focus:ring-brand-700 dark:border-white/20 dark:bg-white/10" aria-label="{{ __('Tick all on this page') }}"
                            x-bind:checked="ids.length > 0 && ids.every(id => $wire.selected.includes(id))"
                            x-on:change="$wire.selected = $event.target.checked ? [...new Set([...$wire.selected, ...ids])] : $wire.selected.filter(id => ! ids.includes(id))">
                    </th>
                    @foreach ([$sortable('code', __('Employee')), $sortable('payable_days', __('Days'), true)] as $col)
                        <th @class(['px-3 py-3 font-medium whitespace-nowrap', 'text-right' => $col['right'], 'sticky left-0 z-10 bg-white/80 backdrop-blur dark:bg-brand-950/80' => $col['field'] === 'code'])>
                            <button type="button" wire:click="sort('{{ $col['field'] }}')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                                {{ $col['label'] }}
                                @if ($col['active'])
                                    <flux:icon :name="$col['dir'] === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                                @else
                                    <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                                @endif
                            </button>
                        </th>
                    @endforeach
                    <th class="px-3 py-3 text-right font-medium whitespace-nowrap" title="{{ __('Leave without pay / absent days') }}">{{ __('LWP / Abs.') }}</th>
                    <th class="px-3 py-3 text-right font-medium whitespace-nowrap" title="{{ __('Approved overtime hours') }}">{{ __('OT h') }}</th>
                    @foreach ($heads as $head)
                        <th @class(['px-3 py-3 text-right font-medium whitespace-nowrap', 'text-red-700 dark:text-red-300' => $head->type === HeadType::Deduction, 'text-zinc-400' => $head->type === HeadType::Info]) title="{{ $head->name }} · {{ $head->type->label() }}">{{ $head->code }}</th>
                    @endforeach
                    @foreach ([$sortable('gross', __('Gross'), true), $sortable('deductions', __('Deductions'), true), $sortable('net', __('Net'), true)] as $col)
                        <th class="px-3 py-3 text-right font-medium whitespace-nowrap">
                            <button type="button" wire:click="sort('{{ $col['field'] }}')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                                {{ $col['label'] }}
                                @if ($col['active'])
                                    <flux:icon :name="$col['dir'] === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                                @else
                                    <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                                @endif
                            </button>
                        </th>
                    @endforeach
                    <th class="px-3 py-3 text-right font-medium whitespace-nowrap">{{ __('Previous net') }}</th>
                    <th class="px-3 py-3 text-right font-medium whitespace-nowrap">{{ __('Change') }}</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($rows as $row)
                    @php
                        $before = $previous[$row->employee_id] ?? null;
                        $change = PayrollReview::change($row->net, $before?->net);
                        $failed = $row->calc_status === \App\Modules\Payroll\Enums\PayrollCalcStatus::Failed;
                        $pending = $row->calc_status === \App\Modules\Payroll\Enums\PayrollCalcStatus::Pending;
                    @endphp
                    <tr wire:key="register-{{ $row->id }}" x-bind:class="$wire.selected.includes('{{ $row->id }}') && '!bg-brand-50/70 dark:!bg-white/10'" class="border-b border-white/40 text-zinc-800 last:border-0 hover:bg-white/40 dark:border-white/5 dark:text-zinc-100 dark:hover:bg-white/5">
                        <td class="px-3 py-2">
                            <input type="checkbox" wire:model="selected" value="{{ $row->id }}" class="size-4 rounded border-zinc-300 text-brand-800 focus:ring-brand-700 dark:border-white/20 dark:bg-white/10" aria-label="{{ __('Tick this row') }}">
                        </td>
                        <td class="sticky left-0 z-10 bg-white/80 px-3 py-2 backdrop-blur dark:bg-brand-950/80">
                            <button type="button" x-on:click="$dispatch('show-payroll-employee', { id: {{ $row->id }} })" class="text-left">
                                <span class="font-medium text-brand-800 underline-offset-4 dark:text-brand-200 underline decoration-current/50 hover:decoration-current hover:text-blue-600 dark:hover:text-blue-400">{{ $row->employee->code ?? '?' }}</span>
                                <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $row->employee->name ?? '' }}</span>
                            </button>
                            @if ($failed)
                                <x-ui.badge color="danger">{{ __('Failed') }}</x-ui.badge>
                            @elseif ($pending)
                                <x-ui.badge color="neutral">{{ __('Not calculated') }}</x-ui.badge>
                            @elseif ($row->hasWarnings())
                                <span class="text-xs text-amber-700 dark:text-amber-300" title="{{ implode("\n", $row->warnings) }}">{{ trans_choice(':count warning|:count warnings', count($row->warnings)) }}</span>
                            @endif
                        </td>
                        @if ($failed || $pending)
                            <td colspan="{{ 3 + $heads->count() + 5 }}" class="px-3 py-2 text-sm {{ $failed ? 'text-red-600 dark:text-red-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                                {{ $failed ? $row->error : __('Not calculated yet.') }}
                            </td>
                        @else
                            <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ $num($row->payable_days) }}<span class="text-zinc-400">/{{ $num($row->period_days) }}</span></td>
                            <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ (float) $row->lwp_days + (float) $row->absent_days > 0 ? $num($row->lwp_days).' / '.$num($row->absent_days) : '' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ (float) $row->overtime_hours > 0 ? $num($row->overtime_hours) : '' }}</td>
                            @foreach ($heads as $head)
                                <td @class(['px-3 py-2 text-right tabular-nums whitespace-nowrap', 'text-zinc-400' => $head->type === HeadType::Info])>{{ isset($cells[$row->id][$head->id]) ? $cells[$row->id][$head->id]->format() : '' }}</td>
                            @endforeach
                            <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ $row->gross->format() }}</td>
                            <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ $row->deductions->format() }}</td>
                            <td class="px-3 py-2 text-right font-medium tabular-nums whitespace-nowrap">{{ $row->net->format() }}</td>
                            <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap text-zinc-500 dark:text-zinc-400">{{ $before?->net->format() ?? '—' }}</td>
                            <td @class(['px-3 py-2 text-right tabular-nums whitespace-nowrap', 'font-medium text-amber-700 dark:text-amber-300' => PayrollReview::isVariance($change['percent']), 'text-zinc-400' => $before === null])>{{ PayrollReview::percentLabel($change['percent']) }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>

            @if ($rows->isNotEmpty())
                <tfoot>
                    <tr class="border-t-2 border-white/60 font-medium text-zinc-900 dark:border-white/15 dark:text-white">
                        <td></td>
                        <td class="sticky left-0 z-10 bg-white/80 px-3 py-3 backdrop-blur dark:bg-brand-950/80">{{ __('Total') }}</td>
                        <td colspan="3"></td>
                        @foreach ($heads as $head)
                            <td class="px-3 py-3 text-right tabular-nums whitespace-nowrap">{{ isset($headTotals[$head->id]) ? $headTotals[$head->id]->format() : '' }}</td>
                        @endforeach
                        <td class="px-3 py-3 text-right tabular-nums whitespace-nowrap">{{ $totals['gross']->format() }}</td>
                        <td class="px-3 py-3 text-right tabular-nums whitespace-nowrap">{{ $totals['deductions']->format() }}</td>
                        <td class="px-3 py-3 text-right tabular-nums whitespace-nowrap">{{ $totals['net']->format() }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            @endif
        </table>

        @if ($rows->isEmpty())
            <div class="px-6 py-12 text-center">
                <flux:heading size="lg">{{ __('No results') }}</flux:heading>
            </div>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-4 border-t border-white/50 px-6 py-4 text-sm text-zinc-600 dark:border-white/10 dark:text-zinc-300">
        <div class="flex items-center gap-2">
            <span>{{ __('Rows per page') }}</span>
            <div class="w-24">
                <flux:select wire:model.live="perPage" size="sm" :aria-label="__('Rows per page')">
                    @foreach ([25, 50, 100, 250] as $size)
                        <flux:select.option value="{{ $size }}">{{ $size }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        <div class="ms-auto">{{ $rows->links() }}</div>
    </div>
</div>
