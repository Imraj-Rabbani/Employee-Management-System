{{-- LeaveBalanceTable list. PHP side: App\Modules\Hrm\Livewire\Leave\LeaveBalanceTable --}}
@use('App\Modules\Hrm\Models\LeaveApplication')
@use('App\Modules\Hrm\Data\LeaveYear')
<div class="glass overflow-hidden rounded-2xl">

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-3 border-b border-white/50 p-4 dark:border-white/10">
        <div class="w-full sm:w-44">
            <flux:select wire:model.live="year" :aria-label="__('Leave year')">
                <flux:select.option value="">{{ __('All') }}: {{ __('Leave year') }}</flux:select.option>
                @foreach ($yearOptions as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="w-full sm:w-44">
            <flux:select wire:model.live="type" :aria-label="__('Leave type')">
                <flux:select.option value="">{{ __('All') }}: {{ __('Leave type') }}</flux:select.option>
                @foreach ($typeOptions as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="w-full sm:w-44">
            <flux:select wire:model.live="entity" :aria-label="__('Entity')">
                <flux:select.option value="">{{ __('All') }}: {{ __('Entity') }}</flux:select.option>
                @foreach ($entityOptions as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($year !== '' || $type !== '' || $entity !== '')
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
                        <button type="button" wire:click="sort('employee_id')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Employee') }}
                            @if ($sortBy === 'employee_id')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('leave_type_id')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Leave type') }}
                            @if ($sortBy === 'leave_type_id')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('year')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Year') }}
                            @if ($sortBy === 'year')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium text-right">{{ __('Opening') }}</th>
                    <th class="px-4 py-3 font-medium text-right">{{ __('Earned') }}</th>
                    <th class="px-4 py-3 font-medium text-right">{{ __('Carried') }}</th>
                    <th class="px-4 py-3 font-medium text-right">{{ __('Corrections') }}</th>
                    <th class="px-4 py-3 font-medium whitespace-nowrap text-right">
                        <button type="button" wire:click="sort('taken')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Taken') }}
                            @if ($sortBy === 'taken')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium text-right">{{ __('Encashed') }}</th>
                    <th class="px-4 py-3 font-medium text-right">{{ __('Expired') }}</th>
                    <th class="px-4 py-3 font-medium whitespace-nowrap text-right">
                        <button type="button" wire:click="sort('balance')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Balance') }}
                            @if ($sortBy === 'balance')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                </tr>
            </thead>

            <tbody>
                @foreach ($rows as $leaveBalance)
                    <tr wire:key="leave-balance-{{ $leaveBalance->getKey() }}" class="border-b border-white/40 text-zinc-800 last:border-0 hover:bg-white/40 dark:border-white/5 dark:text-zinc-100 dark:hover:bg-white/5">
                        <td class="px-4 py-3">{{ $leaveBalance->employee ? $leaveBalance->employee->code.' · '.$leaveBalance->employee->name : '—' }}</td>
                        <td class="px-4 py-3">{{ $leaveBalance->leaveType->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ LeaveYear::of($leaveBalance->year)->label() }}</td>
                        <td class="px-4 py-3 text-right">{{ LeaveApplication::daysLabel($leaveBalance->getAttribute('opening')) }}</td>
                        <td class="px-4 py-3 text-right">{{ LeaveApplication::daysLabel($leaveBalance->getAttribute('earned')) }}</td>
                        <td class="px-4 py-3 text-right">{{ LeaveApplication::daysLabel($leaveBalance->getAttribute('carried_forward')) }}</td>
                        <td class="px-4 py-3 text-right">{{ LeaveApplication::daysLabel($leaveBalance->getAttribute('adjusted')) }}</td>
                        <td class="px-4 py-3 text-right">{{ LeaveApplication::daysLabel($leaveBalance->getAttribute('taken')) }}</td>
                        <td class="px-4 py-3 text-right">{{ LeaveApplication::daysLabel($leaveBalance->getAttribute('encashed')) }}</td>
                        <td class="px-4 py-3 text-right">{{ LeaveApplication::daysLabel($leaveBalance->getAttribute('expired')) }}</td>
                        <td class="px-4 py-3 text-right">{{ $leaveBalance->leaveType?->requires_balance === false ? '—' : LeaveApplication::daysLabel($leaveBalance->balance) }}</td>
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
