{{-- PayrollRunTable list. PHP side: App\Modules\Payroll\Livewire\PayrollRunTable --}}
{{-- wire:poll only while a run on this page is processing: the "12 / 40" and the status move by themselves. --}}
<div class="glass overflow-hidden rounded-2xl" @if ($busy) wire:poll.3s @endif>

    {{-- Toolbar: actions on the left, Filter on the right. $wire.selected = the ticked rows' ids. --}}
    @php $activeFilters = count(array_filter([$search !== '', $year !== '', $runType !== '', $status !== ''])); @endphp
    <div class="flex flex-wrap items-center gap-2 border-b border-white/50 p-4 dark:border-white/10">
        @can('payroll.run.process')
            <flux:button variant="primary" size="sm" icon="plus" x-on:click="$dispatch('create-payroll-run')">{{ __('Add new') }}</flux:button>
            <flux:button size="sm" icon="pencil-square" x-bind:disabled="$wire.selected.length !== 1" x-on:click="$dispatch('edit-payroll-run', { id: Number($wire.selected[0]) })">{{ __('Edit') }}</flux:button>
            <flux:button size="sm" icon="trash" x-bind:disabled="$wire.selected.length === 0" x-on:click="$dispatch('confirm', {
                title: {{ Js::from(__('Delete the ticked runs?')) }},
                message: {{ Js::from(__('Only runs not yet submitted can be deleted. The others stay.')) }},
                confirmText: {{ Js::from(__('Delete')) }},
                onConfirm: () => $wire.dispatch('delete-payroll-run', { ids: $wire.selected }),
            })">{{ __('Delete') }}</flux:button>
            {{-- Calculate: one queued job per employee (needs the queue worker). Also calculates a processed run again. --}}
            <flux:button size="sm" icon="play" x-bind:disabled="$wire.selected.length === 0" wire:click="calculate">{{ __('Calculate') }}</flux:button>
        @endcan
        {{-- Progress: how far the calculation is and who failed. --}}
        <flux:button size="sm" icon="chart-bar" x-bind:disabled="$wire.selected.length !== 1" x-on:click="$dispatch('show-payroll-run', { id: Number($wire.selected[0]) })">{{ __('Progress') }}</flux:button>
        <flux:button wire:click="export" size="sm" icon="arrow-down-tray">{{ __('Export') }}</flux:button>
        <span x-show="$wire.selected.length > 0" x-cloak class="text-xs text-zinc-500 dark:text-zinc-400" x-text="$wire.selected.length + ' ' + {{ Js::from(__('selected')) }}"></span>

        <flux:spacer />

        <flux:modal.trigger name="payroll-run-filters">
            <flux:button size="sm" icon="funnel">{{ __('Filter') }}{{ $activeFilters > 0 ? " ($activeFilters)" : '' }}</flux:button>
        </flux:modal.trigger>
    </div>

    {{-- The Filter form. wire:model (not .live): the list only changes when Apply is clicked. --}}
    <x-ui.modal name="payroll-run-filters" :heading="__('Filter')">
        <div class="space-y-6">
            <flux:field>
                <flux:label>{{ __('Search') }}</flux:label>
                <flux:input wire:model="search" icon="magnifying-glass" :placeholder="__('e.g. PR-2026-12')" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Year') }}</flux:label>
                <flux:select wire:model="year">
                    <flux:select.option value="">{{ __('All') }}</flux:select.option>
                    @foreach ($years as $optionYear)
                        <flux:select.option :value="$optionYear">{{ $optionYear }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Type') }}</flux:label>
                <flux:select wire:model="runType">
                    <flux:select.option value="">{{ __('All') }}</flux:select.option>
                    @foreach (\App\Modules\Payroll\Enums\PayrollRunType::options() as $optionValue => $optionLabel)
                        <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Status') }}</flux:label>
                <flux:select wire:model="status">
                    <flux:select.option value="">{{ __('All') }}</flux:select.option>
                    @foreach (\App\Modules\Payroll\Enums\PayrollRunStatus::options() as $optionValue => $optionLabel)
                        <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
        </div>

        <x-slot:footer>
            <flux:button wire:click="clearFilters" variant="ghost">{{ __('Clear') }}</flux:button>
            <flux:button wire:click="applyFilters" variant="primary">{{ __('Apply') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- The table --}}
    <div class="overflow-x-auto" wire:loading.class="opacity-60">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-white/50 text-left text-zinc-600 dark:border-white/10 dark:text-zinc-300">
                    <th class="w-10 px-4 py-3" x-data="{ ids: {{ Js::from($rows->map(fn ($row) => (string) $row->getKey())->values()) }} }">
                        <input type="checkbox" class="size-4 rounded border-zinc-300 text-brand-800 focus:ring-brand-700 dark:border-white/20 dark:bg-white/10" aria-label="{{ __('Tick all on this page') }}"
                            x-bind:checked="ids.length > 0 && ids.every(id => $wire.selected.includes(id))"
                            x-on:change="$wire.selected = $event.target.checked ? [...new Set([...$wire.selected, ...ids])] : $wire.selected.filter(id => ! ids.includes(id))">
                    </th>
                    @foreach (['code' => __('Code'), 'period_start' => __('Period')] as $field => $label)
                        <th class="px-4 py-3 font-medium whitespace-nowrap">
                            <button type="button" wire:click="sort('{{ $field }}')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                                {{ $label }}
                                @if ($sortBy === $field)
                                    <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                                @else
                                    <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                                @endif
                            </button>
                        </th>
                    @endforeach
                    <th class="px-4 py-3 font-medium">{{ __('Type') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Scope') }}</th>
                    @foreach (['employee_count' => __('Employees'), 'total_net' => __('Net')] as $field => $label)
                        <th class="px-4 py-3 text-right font-medium whitespace-nowrap">
                            <button type="button" wire:click="sort('{{ $field }}')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                                {{ $label }}
                                @if ($sortBy === $field)
                                    <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                                @else
                                    <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                                @endif
                            </button>
                        </th>
                    @endforeach
                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('pay_date')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Pay date') }}
                            @if ($sortBy === 'pay_date')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>
                    <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($rows as $run)
                    <tr wire:key="payroll-run-{{ $run->getKey() }}" x-bind:class="$wire.selected.includes('{{ $run->getKey() }}') && '!bg-brand-50/70 dark:!bg-white/10'" class="border-b border-white/40 text-zinc-800 last:border-0 hover:bg-white/40 dark:border-white/5 dark:text-zinc-100 dark:hover:bg-white/5">
                        <td class="px-4 py-3">
                            <input type="checkbox" wire:model="selected" value="{{ $run->getKey() }}" class="size-4 rounded border-zinc-300 text-brand-800 focus:ring-brand-700 dark:border-white/20 dark:bg-white/10" aria-label="{{ __('Tick this row') }}">
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @can('payroll.run.process')
                                <button type="button" x-on:click="$dispatch('edit-payroll-run', { id: {{ $run->id }} })" class="font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $run->code }}</button>
                            @else
                                {{ $run->code }}
                            @endcan
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $run->period()->label() }}</td>
                        <td class="px-4 py-3">{{ $run->run_type->label() }}@if ($run->head) <span class="text-zinc-500 dark:text-zinc-400">· {{ $run->head->name }}</span>@endif</td>
                        <td class="px-4 py-3">{{ $run->entity->name ?? __('Whole company') }}</td>
                        <td class="px-4 py-3 text-right">{{ $run->employee_count }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">{{ $run->total_net->format() }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ format_date($run->pay_date) ?: '-' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{-- Click: the calculation's progress and failures. --}}
                            <button type="button" x-on:click="$dispatch('show-payroll-run', { id: {{ $run->id }} })" class="inline-flex items-center gap-2" title="{{ __('Show the calculation') }}">
                                <x-ui.badge :color="$run->status->color()">{{ $run->status->label() }}</x-ui.badge>
                                @if ($run->status === \App\Modules\Payroll\Enums\PayrollRunStatus::Processing)
                                    <span class="text-xs tabular-nums text-zinc-500 dark:text-zinc-400">{{ $run->done_count }} / {{ $run->employee_count }}</span>
                                @endif
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if ($rows->isEmpty())
            <div class="px-6 py-12 text-center">
                <flux:heading size="lg">{{ __('No results') }}</flux:heading>
            </div>
        @endif
    </div>

    {{-- Footer: page size and page links --}}
    <div class="flex flex-wrap items-center gap-4 border-t border-white/50 px-6 py-4 text-sm text-zinc-600 dark:border-white/10 dark:text-zinc-300">
        <div class="flex items-center gap-2">
            <span>{{ __('Rows per page') }}</span>
            <div class="w-20">
                <flux:select wire:model.live="perPage" size="sm" :aria-label="__('Rows per page')">
                    <flux:select.option value="10">10</flux:select.option>
                    <flux:select.option value="25">25</flux:select.option>
                    <flux:select.option value="50">50</flux:select.option>
                    <flux:select.option value="100">100</flux:select.option>
                </flux:select>
            </div>
        </div>

        <div class="ms-auto">{{ $rows->links() }}</div>
    </div>
</div>
