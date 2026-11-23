{{-- AdjustmentTable list. PHP side: App\Modules\Payroll\Livewire\AdjustmentTable --}}
<div class="glass overflow-hidden rounded-2xl">

    {{-- Toolbar: actions on the left, Filter on the right. $wire.selected = ticked ids (Alpine, no round trip). --}}
    @php $activeFilters = count(array_filter([$search !== '', $year !== '', $month !== '', $head !== '', $status !== ''])); @endphp
    <div class="flex flex-wrap items-center gap-2 border-b border-white/50 p-4 dark:border-white/10">
        @can('payroll.adjustment.manage')
            <flux:button variant="primary" size="sm" icon="plus" x-on:click="$dispatch('create-adjustment')">{{ __('Add new') }}</flux:button>
            <flux:button size="sm" icon="pencil-square" x-bind:disabled="$wire.selected.length !== 1" x-on:click="$dispatch('edit-adjustment', { id: Number($wire.selected[0]) })">{{ __('Edit') }}</flux:button>
            <flux:button size="sm" icon="arrow-up-tray" x-on:click="$dispatch('import-adjustments')">{{ __('Import') }}</flux:button>
        @endcan
        <flux:button wire:click="export" size="sm" icon="arrow-down-tray">{{ __('Export') }}</flux:button>
        <span x-show="$wire.selected.length > 0" x-cloak class="text-xs text-zinc-500 dark:text-zinc-400" x-text="$wire.selected.length + ' ' + {{ Js::from(__('selected')) }}"></span>

        <flux:spacer />

        <flux:modal.trigger name="adjustment-filters">
            <flux:button size="sm" icon="funnel">{{ __('Filter') }}{{ $activeFilters > 0 ? " ($activeFilters)" : '' }}</flux:button>
        </flux:modal.trigger>
    </div>

    {{-- The Filter form. wire:model (not .live): the list only changes when Apply is clicked. --}}
    <x-ui.modal name="adjustment-filters" :heading="__('Filter')">
        <flux:field>
            <flux:label>{{ __('Search') }}</flux:label>
            <flux:input wire:model="search" icon="magnifying-glass" :placeholder="__('Employee or reason…')" />
        </flux:field>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('Year') }}</flux:label>
                <flux:select wire:model="year">
                    <flux:select.option value="">{{ __('All') }}</flux:select.option>
                    @foreach ($years as $optionValue)
                        <flux:select.option value="{{ $optionValue }}">{{ $optionValue }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Month') }}</flux:label>
                <flux:select wire:model="month">
                    <flux:select.option value="">{{ __('All') }}</flux:select.option>
                    @foreach (range(1, 12) as $m)
                        <flux:select.option value="{{ $m }}">{{ \Carbon\Carbon::create(2000, $m)->translatedFormat('F') }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
        </div>

        <flux:field>
            <flux:label>{{ __('Head') }}</flux:label>
            <flux:select wire:model="head">
                <flux:select.option value="">{{ __('All') }}</flux:select.option>
                @foreach ($heads as $optionValue => $optionLabel)
                    <flux:select.option value="{{ $optionValue }}">{{ $optionLabel }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Status') }}</flux:label>
            <flux:select wire:model="status">
                <flux:select.option value="">{{ __('All') }}</flux:select.option>
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
                    <th class="w-10 px-4 py-3" x-data="{ ids: {{ Js::from($rows->map(fn ($row) => (string) $row->getKey())->values()) }} }">
                        <input type="checkbox" class="size-4 rounded border-zinc-300 text-brand-800 focus:ring-brand-700 dark:border-white/20 dark:bg-white/10" aria-label="{{ __('Tick all on this page') }}"
                            x-bind:checked="ids.length > 0 && ids.every(id => $wire.selected.includes(id))"
                            x-on:change="$wire.selected = $event.target.checked ? [...new Set([...$wire.selected, ...ids])] : $wire.selected.filter(id => ! ids.includes(id))">
                    </th>
                    @foreach (['id' => __('No.'), 'employee' => __('Employee'), 'month' => __('Month'), 'head' => __('Head'), 'amount' => __('Amount'), 'reason' => __('Reason'), 'status' => __('Status'), 'created_at' => __('Asked'), 'actions' => ''] as $column => $label)
                        <th @class(['px-4 py-3 font-medium whitespace-nowrap', 'text-right' => $column === 'amount'])>
                            @if (in_array($column, ['id', 'amount', 'created_at'], true))
                                <button type="button" wire:click="sort('{{ $column }}')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                                    {{ $label }}
                                    @if ($sortBy === $column)
                                        <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                                    @else
                                        <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                                    @endif
                                </button>
                            @else
                                {{ $label }}
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>

            <tbody>
                @foreach ($rows as $adjustment)
                    @php [$badgeLabel, $badgeColor] = $adjustment->statusBadge(); @endphp
                    <tr wire:key="adjustment-{{ $adjustment->id }}" x-bind:class="$wire.selected.includes('{{ $adjustment->id }}') && '!bg-brand-50/70 dark:!bg-white/10'" class="border-b border-white/40 text-zinc-800 last:border-0 hover:bg-white/40 dark:border-white/5 dark:text-zinc-100 dark:hover:bg-white/5">
                        <td class="px-4 py-3">
                            <input type="checkbox" wire:model="selected" value="{{ $adjustment->id }}" class="size-4 rounded border-zinc-300 text-brand-800 focus:ring-brand-700 dark:border-white/20 dark:bg-white/10" aria-label="{{ __('Tick this row') }}">
                        </td>
                        <td class="px-4 py-3">
                            <button type="button" x-on:click="$dispatch('edit-adjustment', { id: {{ $adjustment->id }} })" class="text-left font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">
                                {{ $adjustment->number() }}
                            </button>
                        </td>
                        <td class="px-4 py-3">{{ $adjustment->employee ? $adjustment->employee->code.' · '.$adjustment->employee->name : '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $adjustment->period()->label() }}</td>
                        <td class="px-4 py-3">{{ $adjustment->head->name ?? '—' }}</td>
                        <td @class(['px-4 py-3 text-right tabular-nums whitespace-nowrap', 'text-red-600 dark:text-red-400' => $adjustment->head?->type === \App\Modules\Payroll\Enums\HeadType::Deduction])>{{ $adjustment->signedAmount() }}</td>
                        <td class="px-4 py-3">{{ $adjustment->reason }}</td>
                        <td class="px-4 py-3"><x-ui.badge :color="$badgeColor">{{ $badgeLabel }}</x-ui.badge></td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ format_date($adjustment->created_at) }}</td>
                        <td class="px-4 py-3">
                            @can('payroll.adjustment.manage')
                                @if ($adjustment->isActive() && ($adjustment->run === null || $adjustment->run->status->isEditable()))
                                    <flux:button size="xs" variant="ghost" class="!text-red-600" x-on:click="$dispatch('confirm', {
                                        title: @js(__('Withdraw :no?', ['no' => $adjustment->number()])),
                                        message: @js($adjustment->run ? __('Payroll run :run took it: calculate the run again afterwards.', ['run' => $adjustment->run->code]) : __('It will not be paid.')),
                                        confirmText: @js(__('Withdraw')),
                                        onConfirm: () => $dispatch('withdraw-adjustment', { id: {{ $adjustment->id }} }),
                                    })">{{ __('Withdraw') }}</flux:button>
                                @endif
                            @endcan
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
