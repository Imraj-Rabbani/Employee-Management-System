{{-- PunchTable list. PHP side: App\Modules\Payroll\Livewire\Attendance\PunchTable --}}
<div class="glass overflow-hidden rounded-2xl">

    {{-- Toolbar: actions on the left, Filter on the right. --}}
    @php $activeFilters = count(array_filter([$search !== '', $source !== '', $matched !== ''])); @endphp
    <div class="flex flex-wrap items-center gap-2 border-b border-white/50 p-4 dark:border-white/10">
        <flux:button wire:click="export" size="sm" icon="arrow-down-tray">{{ __('Export') }}</flux:button>

        <flux:spacer />

        <flux:modal.trigger name="punch-filters">
            <flux:button size="sm" icon="funnel">{{ __('Filter') }}{{ $activeFilters > 0 ? " ($activeFilters)" : '' }}</flux:button>
        </flux:modal.trigger>
    </div>

    {{-- The Filter form. wire:model (not .live): the list only changes when Apply is clicked. --}}
    <x-ui.modal name="punch-filters" :heading="__('Filter')">
        <flux:field>
            <flux:label>{{ __('Search') }}</flux:label>
            <flux:input wire:model="search" icon="magnifying-glass" :placeholder="__('Search…')" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Source') }}</flux:label>
            <flux:select wire:model="source">
                <flux:select.option value="">{{ __('All') }}</flux:select.option>
                @foreach ($sourceOptions as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Employee') }}</flux:label>
            <flux:select wire:model="matched">
                <flux:select.option value="">{{ __('All') }}</flux:select.option>
                @foreach ($matchedOptions as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

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
                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('punched_at')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Time') }}
                            @if ($sortBy === 'punched_at')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium">{{ __('Employee') }}</th>
                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('access_code')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Attendance ID') }}
                            @if ($sortBy === 'access_code')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('source')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Source') }}
                            @if ($sortBy === 'source')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium">{{ __('Device') }}</th>
                    <th class="px-4 py-3 font-medium text-right">{{ __('Distance') }}</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($rows as $punchLog)
                    <tr wire:key="punch-log-{{ $punchLog->getKey() }}" class="border-b border-white/40 text-zinc-800 last:border-0 hover:bg-white/40 dark:border-white/5 dark:text-zinc-100 dark:hover:bg-white/5">
                        <td class="px-4 py-3">{{ format_datetime($punchLog->punched_at) }}</td>
                        <td class="px-4 py-3">{{ $punchLog->employee ? $punchLog->employee->code.' · '.$punchLog->employee->name : __('Unmatched') }}</td>
                        <td class="px-4 py-3">{{ $punchLog->access_code }}</td>
                        <td class="px-4 py-3">{{ $punchLog->source->label() }}</td>
                        <td class="px-4 py-3">{{ $punchLog->device->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">{{ $punchLog->distance_m === null ? '—' : __(':m m', ['m' => number_format($punchLog->distance_m)]) }}</td>
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
