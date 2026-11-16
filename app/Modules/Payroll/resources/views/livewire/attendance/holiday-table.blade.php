{{-- HolidayTable list. PHP side: App\Modules\Payroll\Livewire\Attendance\HolidayTable --}}
<div class="glass overflow-hidden rounded-2xl">

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-3 border-b border-white/50 p-4 dark:border-white/10">
        <div class="w-full sm:w-72">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search…')" clearable />
        </div>

        <div class="w-full sm:w-44">
            <flux:select wire:model.live="year" :aria-label="__('Year')">
                <flux:select.option value="">{{ __('All') }}: {{ __('Year') }}</flux:select.option>
                @foreach ($yearOptions as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="w-full sm:w-44">
            <flux:select wire:model.live="type" :aria-label="__('Type')">
                <flux:select.option value="">{{ __('All') }}: {{ __('Type') }}</flux:select.option>
                @foreach ($typeOptions as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($search !== '' || $year !== '' || $type !== '')
            <flux:button wire:click="clearFilters" variant="ghost" size="sm" icon="x-mark">{{ __('Clear') }}</flux:button>
        @endif

        <flux:spacer />

        <flux:button wire:click="export" size="sm" icon="arrow-down-tray">{{ __('Export') }}</flux:button>
    </div>

    {{-- The table --}}
    <div class="overflow-x-auto" wire:loading.class="opacity-60">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-white/50 text-left text-zinc-600 dark:border-white/10 dark:text-zinc-300">
                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('date')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Date') }}
                            @if ($sortBy === 'date')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('title')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Title') }}
                            @if ($sortBy === 'title')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium">{{ __('Type') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Calendars') }}</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($rows as $holiday)
                    <tr wire:key="holiday-{{ $holiday->getKey() }}" class="border-b border-white/40 text-zinc-800 last:border-0 hover:bg-white/40 dark:border-white/5 dark:text-zinc-100 dark:hover:bg-white/5">
                        <td class="px-4 py-3">{{ $holiday->repeats_yearly
                ? $holiday->date->translatedFormat('j F').' · '.__('every year')
                : format_date($holiday->date).' · '.$holiday->date->translatedFormat('l') }}</td>
                        {{-- Opens the edit form (it listens for "edit-holiday"). --}}
                        <td class="px-4 py-3">
                            <button type="button" x-on:click="$dispatch('edit-holiday', { id: {{ $holiday->getKey() }} })" class="text-left font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">
                                {{ $holiday->title }}
                            </button>
                        </td>
                        <td class="px-4 py-3">
                            @if ($holiday->is_public)
                                <x-ui.badge color="info">{{ __('Public') }}</x-ui.badge>
                            @endif
                            @if ($holiday->is_festival)
                                <x-ui.badge color="warning">{{ __('Festival') }}</x-ui.badge>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $holiday->profiles->pluck('name')->join(', ') ?: '—' }}</td>
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
