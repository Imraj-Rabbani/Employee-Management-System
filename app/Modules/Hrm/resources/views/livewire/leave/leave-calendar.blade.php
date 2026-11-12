{{-- Who is on leave (LeaveCalendar, 8.7): employees × days. Solid = approved, outlined = waiting for approval. --}}
@php $num = fn ($v) => \App\Modules\Hrm\Models\LeaveApplication::daysLabel($v); @endphp
<div class="space-y-4">
    <div class="flex flex-wrap items-end gap-3">
        <div class="flex items-center gap-1">
            <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="previous" :aria-label="__('Previous month')" />
            <span class="min-w-32 text-center text-sm font-medium">{{ $monthStart->translatedFormat('F Y') }}</span>
            <flux:button size="sm" variant="ghost" icon="chevron-right" wire:click="next" :aria-label="__('Next month')" />
        </div>
        <div class="w-64"><x-form.select wire:model.live="orgUnitId" :options="$units" :placeholder="__('All org units')" /></div>
        <div class="w-56"><flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Name or code')" size="sm" /></div>
    </div>

    @if ($tooMany)
        <flux:callout variant="warning" icon="information-circle" :heading="__('Only the first :n employees are shown. Choose an org unit or search to see the others.', ['n' => \App\Modules\Hrm\Livewire\Leave\LeaveCalendar::MAX_ROWS])" />
    @endif

    <x-ui.card :padding="false">
        @if ($employees->isEmpty())
            <x-ui.empty icon="calendar-days" :heading="__('Nobody is on leave in :month', ['month' => $monthStart->translatedFormat('F Y')])" />
        @else
            <div class="max-h-[70vh] overflow-auto">
                <table class="text-[11px]">
                    <thead class="sticky top-0 z-10 bg-white dark:bg-zinc-900">
                        <tr>
                            <th class="sticky left-0 z-20 bg-white px-3 py-2 text-left dark:bg-zinc-900">{{ __('Employee') }}</th>
                            @foreach ($dates as $date)
                                <th @class(['px-0.5 py-2 text-center font-medium', 'text-brand-700 dark:text-brand-200' => $date->toDateString() === $today])>
                                    <div>{{ $date->day }}</div>
                                    <div class="font-normal text-zinc-400">{{ mb_substr($date->translatedFormat('D'), 0, 2) }}</div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($employees as $employee)
                            <tr wire:key="leave-cal-{{ $employee->id }}" class="border-t border-zinc-100 dark:border-white/5">
                                <td class="sticky left-0 bg-white px-3 py-1 whitespace-nowrap dark:bg-zinc-900">
                                    <a href="{{ route('hrm.employees.show', ['employee' => $employee->id, 'tab' => 'leave']) }}" wire:navigate class="text-brand-800 hover:underline dark:text-brand-200">{{ $employee->code }} · {{ $employee->name }}</a>
                                </td>
                                @foreach ($dates as $date)
                                    @php $day = $matrix[$employee->id][$date->toDateString()] ?? null; @endphp
                                    <td class="p-0.5 text-center">
                                        @if ($day)
                                            @php $approved = $day->application->approved_at !== null; @endphp
                                            <button type="button" x-on:click="$dispatch('show-leave', { id: {{ $day->leave_application_id }} })" @class([
                                                'inline-flex h-6 min-w-6 items-center justify-center rounded px-0.5 font-semibold',
                                                'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-200' => $approved,
                                                'text-amber-700 ring-1 ring-inset ring-amber-400 dark:text-amber-300' => ! $approved,
                                            ]) title="{{ format_date($date) }}: {{ $day->application->leaveType?->name }}{{ $day->days < 1 ? ' (½)' : '' }} · {{ $approved ? __('Approved') : __('Waiting for approval') }}">
                                                {{ $day->application->leaveType?->code }}{{ $day->days < 1 ? '½' : '' }}
                                            </button>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        <tr class="border-t-2 border-zinc-200 font-semibold dark:border-white/10">
                            <td class="sticky left-0 bg-white px-3 py-1 dark:bg-zinc-900">{{ __('On leave') }}</td>
                            @foreach ($dates as $date)
                                <td class="p-0.5 text-center text-zinc-600 dark:text-zinc-300">{{ isset($perDay[$date->toDateString()]) ? $num($perDay[$date->toDateString()]) : '' }}</td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    <flux:text class="text-xs">{{ __('Leave type code per day · ½ = half day · filled = approved, outlined = waiting for approval. Click a day to open the application.') }}</flux:text>
</div>
