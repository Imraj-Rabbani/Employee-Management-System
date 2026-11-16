{{-- Setup → Fiscal Years list. PHP side: App\Modules\Core\Livewire\FiscalYearsTable --}}
<div class="glass overflow-hidden rounded-2xl">

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-3 border-b border-white/50 p-4 dark:border-white/10">
        <div class="w-full sm:w-72">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search…')" clearable />
        </div>

        <flux:spacer />

        <flux:button wire:click="export" size="sm" icon="arrow-down-tray">{{ __('Export') }}</flux:button>
    </div>

    {{-- The table --}}
    <div class="overflow-x-auto" wire:loading.class="opacity-60">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-white/50 text-left text-zinc-600 dark:border-white/10 dark:text-zinc-300">
                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('name')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Name') }}
                            @if ($sortBy === 'name')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('start_date')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Starts') }}
                            @if ($sortBy === 'start_date')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('end_date')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Ends') }}
                            @if ($sortBy === 'end_date')
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
                @foreach ($rows as $year)
                    <tr wire:key="year-{{ $year->id }}" class="border-b border-white/40 text-zinc-800 last:border-0 hover:bg-white/40 dark:border-white/5 dark:text-zinc-100 dark:hover:bg-white/5">
                        {{-- The name opens the Edit form (FiscalYearForm listens for edit-fiscal-year). --}}
                        <td class="px-4 py-3">
                            <button type="button" x-on:click="$dispatch('edit-fiscal-year', { id: {{ $year->id }} })" class="font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">
                                {{ $year->name }}
                            </button>
                        </td>

                        <td class="px-4 py-3">{{ format_date($year->start_date) }}</td>
                        <td class="px-4 py-3">{{ format_date($year->end_date) }}</td>

                        <td class="px-4 py-3">
                            @if ($year->is_active)
                                <x-ui.badge color="success" dot>{{ __('Active') }}</x-ui.badge>
                            @else
                                <flux:button size="xs" variant="ghost" x-on:click="$dispatch('confirm', {
                                    title: @js(__('Make :name the active year?', ['name' => $year->name])),
                                    message: @js(__('Tax and reports will use this year from now on.')),
                                    confirmText: @js(__('Make active')),
                                    onConfirm: () => $wire.activate({{ $year->id }}),
                                })">{{ __('Make active') }}</flux:button>
                            @endif
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
