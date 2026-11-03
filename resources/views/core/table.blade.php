{{-- Shared view for every App\Core\Livewire\Table\DataTable. --}}
<div>
    <x-ui.card :padding="false">
        {{-- Toolbar: search, filters, columns, export --}}
        <div class="flex flex-wrap items-center gap-3 border-b border-white/50 p-4 dark:border-white/10">
            <div class="w-full sm:w-72">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search…')" clearable />
            </div>

            @foreach ($filterList as $filter)
                <div class="w-full sm:w-44">
                    <flux:select wire:model.live="filters.{{ $filter->key }}" :aria-label="$filter->label">
                        <flux:select.option value="">{{ __('All') }}: {{ $filter->label }}</flux:select.option>
                        @foreach ($filter->options as $value => $text)
                            <flux:select.option :value="$value">{{ $text }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            @endforeach

            @if ($hasActiveFilters)
                <flux:button wire:click="clearFilters" variant="ghost" size="sm" icon="x-mark">{{ __('Clear') }}</flux:button>
            @endif

            <flux:spacer />

            <flux:dropdown align="end">
                <flux:button size="sm" icon="view-columns">{{ __('Columns') }}</flux:button>

                <flux:menu>
                    @foreach ($allColumns as $column)
                        @if ($column->toggleable)
                            <flux:menu.checkbox wire:click="toggleColumn('{{ $column->field }}')" :checked="! in_array($column->field, $hiddenColumns, true)" keep-open>
                                {{ $column->label }}
                            </flux:menu.checkbox>
                        @endif
                    @endforeach
                </flux:menu>
            </flux:dropdown>

            <flux:button wire:click="export" size="sm" icon="arrow-down-tray">{{ __('Export') }}</flux:button>
        </div>

        {{-- Bulk action bar, only while rows are selected --}}
        @if ($bulkActionList && count($selected) > 0)
            <div class="flex flex-wrap items-center gap-3 border-b border-white/50 bg-brand-800/5 px-4 py-2 text-sm dark:border-white/10 dark:bg-white/5">
                <span class="font-medium">{{ trans_choice(':count selected|:count selected', count($selected), ['count' => count($selected)]) }}</span>

                @foreach ($bulkActionList as $key => $action)
                    @if (isset($action['confirm']))
                        <flux:button size="sm" :variant="$action['variant'] ?? 'filled'" x-on:click="$dispatch('confirm', {
                            title: @js($action['confirm']),
                            confirmText: @js($action['label']),
                            onConfirm: () => $wire.runBulkAction(@js($key)),
                        })">{{ $action['label'] }}</flux:button>
                    @else
                        <flux:button size="sm" :variant="$action['variant'] ?? 'filled'" wire:click="runBulkAction('{{ $key }}')">{{ $action['label'] }}</flux:button>
                    @endif
                @endforeach

                <flux:button wire:click="clearSelection" size="sm" variant="ghost">{{ __('Clear selection') }}</flux:button>
            </div>
        @endif

        {{-- Table --}}
        <div class="overflow-x-auto" wire:loading.class="opacity-60">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-white/50 text-left dark:border-white/10">
                        @if ($bulkActionList)
                            <th class="w-10 px-4 py-3">
                                <flux:checkbox wire:model.live="selectPage" :aria-label="__('Select all on this page')" />
                            </th>
                        @endif

                        @foreach ($columns as $column)
                            <th @class(['px-4 py-3 font-medium whitespace-nowrap text-zinc-600 dark:text-zinc-300', 'text-right' => $column->alignRight])>
                                @if ($column->sortable)
                                    <button type="button" wire:click="sort('{{ $column->field }}')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                                        {{ $column->label }}

                                        @if ($sortBy === $column->field)
                                            <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                                        @else
                                            <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                                        @endif
                                    </button>
                                @else
                                    {{ $column->label }}
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @foreach ($rows as $row)
                        <tr wire:key="row-{{ $row->getKey() }}" class="border-b border-white/40 last:border-0 hover:bg-white/40 dark:border-white/5 dark:hover:bg-white/5">
                            @if ($bulkActionList)
                                <td class="px-4 py-3">
                                    <flux:checkbox wire:model.live="selected" value="{{ $row->getKey() }}" :aria-label="__('Select row')" />
                                </td>
                            @endif

                            @foreach ($columns as $column)
                                <td @class(['px-4 py-3 text-zinc-800 dark:text-zinc-100', 'text-right' => $column->alignRight])>
                                    {{ $column->value($row) }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($rows->isEmpty())
                <x-ui.empty icon="magnifying-glass" :heading="__('No results')" :description="$hasActiveFilters ? __('Try a different search or clear the filters.') : __('There is nothing here yet.')">
                    @if ($hasActiveFilters)
                        <flux:button wire:click="clearFilters" size="sm">{{ __('Clear filters') }}</flux:button>
                    @endif
                </x-ui.empty>
            @endif
        </div>

        {{-- Footer: page size, count, page links --}}
        <x-slot:footer>
            <div class="flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-2">
                    <span>{{ __('Rows per page') }}</span>
                    <div class="w-20">
                        <flux:select wire:model.live="perPage" size="sm" :aria-label="__('Rows per page')">
                            @foreach (\App\Core\Livewire\Table\DataTable::PER_PAGE_OPTIONS as $size)
                                <flux:select.option :value="$size">{{ $size }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>

                <div class="ms-auto">
                    {{ $rows->links() }}
                </div>
            </div>
        </x-slot:footer>
    </x-ui.card>
</div>
