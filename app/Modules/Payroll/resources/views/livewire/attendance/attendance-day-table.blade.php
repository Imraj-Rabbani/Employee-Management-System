{{-- AttendanceDayTable list. PHP side: App\Modules\Payroll\Livewire\Attendance\AttendanceDayTable --}}
@use('App\Modules\Payroll\Models\AttendanceDay')
<div class="glass overflow-hidden rounded-2xl">

    {{-- Toolbar: actions on the left, Filter on the right. --}}
    @php $activeFilters = count(array_filter([$month !== '', $status !== '', $entity !== ''])); @endphp
    <div class="flex flex-wrap items-center gap-2 border-b border-white/50 p-4 dark:border-white/10">
        <flux:button wire:click="export" size="sm" icon="arrow-down-tray">{{ __('Export') }}</flux:button>

        <flux:spacer />

        <flux:modal.trigger name="attendance-day-filters">
            <flux:button size="sm" icon="funnel">{{ __('Filter') }}{{ $activeFilters > 0 ? " ($activeFilters)" : '' }}</flux:button>
        </flux:modal.trigger>
    </div>

    {{-- The Filter form. wire:model (not .live): the list only changes when Apply is clicked. --}}
    <x-ui.modal name="attendance-day-filters" :heading="__('Filter')">

        <flux:field>
            <flux:label>{{ __('Month') }}</flux:label>
            <flux:select wire:model="month">
                <flux:select.option value="">{{ __('All') }}</flux:select.option>
                @foreach ($monthOptions as $value => $label)
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
            <flux:label>{{ __('Entity') }}</flux:label>
            <flux:select wire:model="entity">
                <flux:select.option value="">{{ __('All') }}</flux:select.option>
                @foreach ($entityOptions as $value => $label)
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
                        <button type="button" wire:click="sort('date')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Date') }}
                            @if ($sortBy === 'date')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium">{{ __('Employee') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Shift') }}</th>
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

                    <th class="px-4 py-3 font-medium">{{ __('In') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Out') }}</th>
                    <th class="px-4 py-3 font-medium whitespace-nowrap text-right">
                        <button type="button" wire:click="sort('worked_minutes')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Worked') }}
                            @if ($sortBy === 'worked_minutes')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium whitespace-nowrap text-right">
                        <button type="button" wire:click="sort('late_minutes')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Late') }}
                            @if ($sortBy === 'late_minutes')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium text-right">{{ __('Early exit') }}</th>
                    <th class="px-4 py-3 font-medium whitespace-nowrap text-right">
                        <button type="button" wire:click="sort('ot_minutes')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Overtime') }}
                            @if ($sortBy === 'ot_minutes')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium">{{ __('Note') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Processed') }}</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($rows as $attendanceDay)
                    <tr wire:key="attendance-day-{{ $attendanceDay->getKey() }}" class="border-b border-white/40 text-zinc-800 last:border-0 hover:bg-white/40 dark:border-white/5 dark:text-zinc-100 dark:hover:bg-white/5">
                        <td class="px-4 py-3">{{ format_date($attendanceDay->date).' · '.$attendanceDay->date->translatedFormat('D') }}</td>
                        <td class="px-4 py-3">{{ $attendanceDay->employee ? $attendanceDay->employee->code.' · '.$attendanceDay->employee->name : '—' }}</td>
                        <td class="px-4 py-3">{{ $attendanceDay->shift->code ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <x-ui.badge :color="$attendanceDay->status->color()">{{ $attendanceDay->status->label() }}</x-ui.badge>
                            @if ($attendanceDay->is_late) <x-ui.badge color="warning">{{ __('Late') }}</x-ui.badge> @endif
                            @if ($attendanceDay->is_corrected) <x-ui.badge color="info">{{ __('Corrected') }}</x-ui.badge> @endif
                            @if ($attendanceDay->is_locked) <x-ui.badge color="neutral">{{ __('Locked') }}</x-ui.badge> @endif
                        </td>
                        <td class="px-4 py-3">{{ ($attendanceDay->in_at === null ? '—' : $attendanceDay->in_at->format('H:i')) }}</td>
                        <td class="px-4 py-3">{{ ($attendanceDay->out_at === null ? '—' : $attendanceDay->out_at->format('H:i')) }}</td>
                        <td class="px-4 py-3 text-right">{{ ($attendanceDay->worked_minutes === 0 ? '—' : AttendanceDay::hoursLabel($attendanceDay->worked_minutes)) }}</td>
                        <td class="px-4 py-3 text-right">{{ ($attendanceDay->late_minutes === 0 ? '—' : AttendanceDay::hoursLabel($attendanceDay->late_minutes)) }}</td>
                        <td class="px-4 py-3 text-right">{{ ($attendanceDay->early_exit_minutes === 0 ? '—' : AttendanceDay::hoursLabel($attendanceDay->early_exit_minutes)) }}</td>
                        <td class="px-4 py-3 text-right">{{ ($attendanceDay->ot_minutes === 0 ? '—' : AttendanceDay::hoursLabel($attendanceDay->ot_minutes)) }}</td>
                        <td class="px-4 py-3">{{ $attendanceDay->note ?? '' }}</td>
                        <td class="px-4 py-3">{{ $attendanceDay->processed_at ? format_datetime($attendanceDay->processed_at) : '—' }}</td>
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
