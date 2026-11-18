{{-- RosterTable list. PHP side: App\Modules\Payroll\Livewire\Attendance\RosterTable --}}
<div class="glass overflow-hidden rounded-2xl">

    {{-- Toolbar: actions on the left, Filter on the right.
         The buttons work on the ticked rows: $wire.selected = their ids (Alpine reads it, no round trip). --}}
    @php $activeFilters = count(array_filter([$search !== ''])); @endphp
    <div class="flex flex-wrap items-center gap-2 border-b border-white/50 p-4 dark:border-white/10">
        <flux:button variant="primary" size="sm" icon="plus" x-on:click="$dispatch('create-roster')">{{ __('Add new') }}</flux:button>
        {{-- Edit: exactly one row ticked. --}}
        <flux:button size="sm" icon="pencil-square" x-bind:disabled="$wire.selected.length !== 1" x-on:click="$dispatch('edit-roster', { id: Number($wire.selected[0]) })">{{ __('Edit') }}</flux:button>
        {{-- Delete: one or more rows. The form (delete-roster) deletes those that may go and says which stayed. --}}
        <flux:button size="sm" icon="trash" x-bind:disabled="$wire.selected.length === 0" x-on:click="$dispatch('confirm', {
            title: {{ Js::from(__('Delete the ticked rows?')) }},
            message: {{ Js::from(__('Rows that are still in use stay.')) }},
            confirmText: {{ Js::from(__('Delete')) }},
            onConfirm: () => $wire.dispatch('delete-roster', { ids: $wire.selected }),
        })">{{ __('Delete') }}</flux:button>
        <flux:button wire:click="export" size="sm" icon="arrow-down-tray">{{ __('Export') }}</flux:button>
        {{-- Request approval: the ticked drafts (or sent back ones) go into their workflow. --}}
        <flux:button size="sm" icon="paper-airplane" x-bind:disabled="$wire.selected.length === 0" wire:click="requestApproval">{{ __('Request approval') }}</flux:button>
        <span x-show="$wire.selected.length > 0" x-cloak class="text-xs text-zinc-500 dark:text-zinc-400" x-text="$wire.selected.length + ' ' + {{ Js::from(__('selected')) }}"></span>

        <flux:spacer />

        <flux:modal.trigger name="roster-filters">
            <flux:button size="sm" icon="funnel">{{ __('Filter') }}{{ $activeFilters > 0 ? " ($activeFilters)" : '' }}</flux:button>
        </flux:modal.trigger>
    </div>

    {{-- The Filter form. wire:model (not .live): the list only changes when Apply is clicked. --}}
    <x-ui.modal name="roster-filters" :heading="__('Filter')">
        <flux:field>
            <flux:label>{{ __('Search') }}</flux:label>
            <flux:input wire:model="search" icon="magnifying-glass" :placeholder="__('Search…')" />
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
                    {{-- Tick all rows of this page (adds to / removes from $wire.selected). --}}
                    <th class="w-10 px-4 py-3" x-data="{ ids: {{ Js::from($rows->map(fn ($row) => (string) $row->getKey())->values()) }} }">
                        <input type="checkbox" class="size-4 rounded border-zinc-300 text-brand-800 focus:ring-brand-700 dark:border-white/20 dark:bg-white/10" aria-label="{{ __('Tick all on this page') }}"
                            x-bind:checked="ids.length > 0 && ids.every(id => $wire.selected.includes(id))"
                            x-on:change="$wire.selected = $event.target.checked ? [...new Set([...$wire.selected, ...ids])] : $wire.selected.filter(id => ! ids.includes(id))">
                    </th>
                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('id')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('No.') }}
                            @if ($sortBy === 'id')
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

                    <th class="px-4 py-3 font-medium">{{ __('Entity') }}</th>
                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('from_date')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('From') }}
                            @if ($sortBy === 'from_date')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('to_date')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('To') }}
                            @if ($sortBy === 'to_date')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium whitespace-nowrap text-right">
                        <button type="button" wire:click="sort('employees_count')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Employees') }}
                            @if ($sortBy === 'employees_count')
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
                @foreach ($rows as $roster)
                    <tr wire:key="roster-{{ $roster->getKey() }}" x-bind:class="$wire.selected.includes('{{ $roster->getKey() }}') && '!bg-brand-50/70 dark:!bg-white/10'" class="border-b border-white/40 text-zinc-800 last:border-0 hover:bg-white/40 dark:border-white/5 dark:text-zinc-100 dark:hover:bg-white/5">
                        <td class="px-4 py-3">
                            <input type="checkbox" wire:model="selected" value="{{ $roster->getKey() }}" class="size-4 rounded border-zinc-300 text-brand-800 focus:ring-brand-700 dark:border-white/20 dark:bg-white/10" aria-label="{{ __('Tick this row') }}">
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('payroll.attendance.rosters.show', $roster->id) }}" wire:navigate class="font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $roster->number() }}</a>
                        </td>
                        <td class="px-4 py-3">{{ $roster->title }}</td>
                        <td class="px-4 py-3">{{ $roster->entity->name ?? '-' }}</td>
                        <td class="px-4 py-3">{{ format_date($roster->from_date) }}</td>
                        <td class="px-4 py-3">{{ format_date($roster->to_date) }}</td>
                        <td class="px-4 py-3 text-right">{{ $roster->employees_count }}</td>
                        <td class="px-4 py-3">
                            <x-ui.badge :color="$roster->statusBadge()[1]">{{ $roster->statusBadge()[0] }}</x-ui.badge>
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
