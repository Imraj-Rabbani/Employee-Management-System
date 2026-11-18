{{-- EmployeeTable list. PHP side: App\Modules\Hrm\Livewire\Employees\EmployeeTable --}}
<div class="glass overflow-hidden rounded-2xl">

    {{-- Toolbar: actions on the left, Filter on the right.
         The buttons work on the ticked rows: $wire.selected = their ids (Alpine reads it, no round trip). --}}
    @php $activeFilters = count(array_filter([$search !== '', $entity !== '', $status !== '', $gradeId !== '', $employmentTypeId !== ''])); @endphp
    <div class="flex flex-wrap items-center gap-2 border-b border-white/50 p-4 dark:border-white/10">
        @can('create', App\Modules\Hrm\Models\Employee::class)
            <flux:button variant="primary" size="sm" icon="plus" x-on:click="$dispatch('create-employee')">{{ __('Add new') }}</flux:button>
        @endcan
        {{-- Edit: exactly one row ticked. --}}
        <flux:button size="sm" icon="pencil-square" x-bind:disabled="$wire.selected.length !== 1" x-on:click="Livewire.navigate({{ Js::from(route('hrm.employees.show', ['employee' => '__ID__'])) }}.replace('__ID__', $wire.selected[0]))">{{ __('Edit') }}</flux:button>
        <flux:button wire:click="export" size="sm" icon="arrow-down-tray">{{ __('Export') }}</flux:button>
        <span x-show="$wire.selected.length > 0" x-cloak class="text-xs text-zinc-500 dark:text-zinc-400" x-text="$wire.selected.length + ' ' + {{ Js::from(__('selected')) }}"></span>

        <flux:spacer />

        <flux:modal.trigger name="employee-filters">
            <flux:button size="sm" icon="funnel">{{ __('Filter') }}{{ $activeFilters > 0 ? " ($activeFilters)" : '' }}</flux:button>
        </flux:modal.trigger>
    </div>

    {{-- The Filter form. wire:model (not .live): the list only changes when Apply is clicked. --}}
    <x-ui.modal name="employee-filters" :heading="__('Filter')">
        <flux:field>
            <flux:label>{{ __('Search') }}</flux:label>
            <flux:input wire:model="search" icon="magnifying-glass" :placeholder="__('Search…')" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Entity') }}</flux:label>
            <flux:select wire:model="entity">
                <flux:select.option value="">{{ __('All') }}</flux:select.option>
                @foreach ($entityOptions as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Status') }}</flux:label>
            <flux:select wire:model="status">
                <flux:select.option value="">{{ __('All') }}</flux:select.option>
                @foreach ($statusOptions as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Grade') }}</flux:label>
            <flux:select wire:model="gradeId">
                <flux:select.option value="">{{ __('All') }}</flux:select.option>
                @foreach ($gradeIdOptions as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Employment type') }}</flux:label>
            <flux:select wire:model="employmentTypeId">
                <flux:select.option value="">{{ __('All') }}</flux:select.option>
                @foreach ($employmentTypeIdOptions as $value => $label)
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
                    {{-- Tick all rows of this page (adds to / removes from $wire.selected). --}}
                    <th class="w-10 px-4 py-3" x-data="{ ids: {{ Js::from($rows->map(fn ($row) => (string) $row->getKey())->values()) }} }">
                        <input type="checkbox" class="size-4 rounded border-zinc-300 text-brand-800 focus:ring-brand-700 dark:border-white/20 dark:bg-white/10" aria-label="{{ __('Tick all on this page') }}"
                            x-bind:checked="ids.length > 0 && ids.every(id => $wire.selected.includes(id))"
                            x-on:change="$wire.selected = $event.target.checked ? [...new Set([...$wire.selected, ...ids])] : $wire.selected.filter(id => ! ids.includes(id))">
                    </th>
                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('code')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Code') }}
                            @if ($sortBy === 'code')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('first_name')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Name') }}
                            @if ($sortBy === 'first_name')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium">{{ __('Designation') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Entities') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Grade') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Employment type') }}</th>
                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('join_date')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Joined') }}
                            @if ($sortBy === 'join_date')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium">{{ __('Mobile') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Office email') }}</th>
                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('status')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Status') }}
                            @if ($sortBy === 'status')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                </tr>
            </thead>

            <tbody>
                @foreach ($rows as $employee)
                    <tr wire:key="employee-{{ $employee->getKey() }}" x-bind:class="$wire.selected.includes('{{ $employee->getKey() }}') && '!bg-brand-50/70 dark:!bg-white/10'" class="border-b border-white/40 text-zinc-800 last:border-0 hover:bg-white/40 dark:border-white/5 dark:text-zinc-100 dark:hover:bg-white/5">
                        <td class="px-4 py-3">
                            <input type="checkbox" wire:model="selected" value="{{ $employee->getKey() }}" class="size-4 rounded border-zinc-300 text-brand-800 focus:ring-brand-700 dark:border-white/20 dark:bg-white/10" aria-label="{{ __('Tick this row') }}">
                        </td>
                        <td class="px-4 py-3">{{ $employee->code }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('hrm.employees.show', $employee) }}" wire:navigate class="font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $employee->name }}</a>
                        </td>
                        <td class="px-4 py-3">{{ $employee->designation->name ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $employee->entitiesLabel(withType: false) ?: '-' }}</td>
                        <td class="px-4 py-3">{{ $employee->grade->name ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $employee->employmentType->name ?? '-' }}</td>
                        <td class="px-4 py-3">{{ format_date($employee->join_date) }}</td>
                        <td class="px-4 py-3">{{ $employee->mobile }}</td>
                        <td class="px-4 py-3">{{ $employee->office_email }}</td>
                        <td class="px-4 py-3">
                            <x-ui.badge :color="$employee->status->color()">{{ $employee->status->label() }}</x-ui.badge>
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
