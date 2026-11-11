{{--
    One employee's month (EmployeeCalendar, 7.8). Processed days: status + in/out + flags.
    Days not processed yet: the plan in grey (holiday, weekend, shift code).
--}}
@php
    use App\Modules\Payroll\Enums\DayType;
    use App\Modules\Payroll\Models\AttendanceDay;
    $tint = [
        'success' => 'bg-green-50 dark:bg-green-500/10',
        'warning' => 'bg-amber-50 dark:bg-amber-500/10',
        'danger' => 'bg-red-50 dark:bg-red-500/10',
        'info' => 'bg-sky-50 dark:bg-sky-500/10',
        'neutral' => 'bg-zinc-50 dark:bg-white/5',
    ];
@endphp
<div>
    {{-- No linked employee record (Self Service): nothing; My Attendance already says so. --}}
    @if ($employee)
        <x-ui.card :heading="$mode === 'self' ? __('My calendar') : $employee->code.' · '.$employee->name" :padding="false">
            <x-slot:actions>
                <div class="flex items-center gap-1">
                    <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="previous" :aria-label="__('Previous month')" />
                    <span class="min-w-32 text-center text-sm font-medium">{{ $monthStart->translatedFormat('F Y') }}</span>
                    <flux:button size="sm" variant="ghost" icon="chevron-right" wire:click="next" :aria-label="__('Next month')" />
                    <flux:button size="sm" variant="ghost" wire:click="thisMonth">{{ __('Today') }}</flux:button>
                </div>
            </x-slot:actions>

            <div class="overflow-x-auto">
                <div class="grid min-w-[640px] grid-cols-7 border-t border-zinc-200 text-xs dark:border-white/10">
                    @foreach ([__('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat'), __('Sun')] as $weekday)
                        <div class="border-b border-zinc-200 px-2 py-1.5 font-medium text-zinc-500 dark:border-white/10">{{ $weekday }}</div>
                    @endforeach

                    @foreach ($weeks as $week)
                        @foreach ($week as $cell)
                            @php
                                /** @var AttendanceDay|null $day */
                                $day = $cell['day'];
                                $cellTint = $day && $cell['inMonth'] ? $tint[$day->status->color()] : '';
                            @endphp
                            <div wire:key="cal-{{ $cell['date']->toDateString() }}" @class([
                                'min-h-20 space-y-0.5 border-b border-e border-zinc-200 p-1.5 dark:border-white/10',
                                'opacity-40' => ! $cell['inMonth'] || ! $cell['employed'],
                                $cellTint => $cellTint !== '',
                            ])>
                                <div class="flex items-center justify-between">
                                    <span @class(['font-medium', 'rounded-full bg-brand-700 px-1.5 text-white' => $cell['isToday']])>{{ $cell['date']->day }}</span>
                                    @if ($day && $cell['inMonth'])
                                        <x-ui.badge :color="$day->status->color()">{{ $day->status->label() }}</x-ui.badge>
                                    @endif
                                </div>

                                @if ($cell['inMonth'] && $cell['employed'])
                                    @if ($cell['holiday'])
                                        <div class="truncate text-zinc-600 dark:text-zinc-300" title="{{ $cell['holiday'] }}">{{ $cell['holiday'] }}</div>
                                    @endif
                                    @if ($day)
                                        @if ($day->in_at || $day->out_at)
                                            <div class="tabular-nums">{{ $day->in_at?->format('H:i') ?? '—' }}–{{ $day->out_at?->format('H:i') ?? '—' }}</div>
                                        @endif
                                        <div class="flex flex-wrap gap-1">
                                            @if ($day->is_late)<span class="text-amber-700 dark:text-amber-300">{{ __('Late') }}</span>@endif
                                            @if ($day->is_corrected)<span class="text-sky-700 dark:text-sky-300">{{ __('Corrected') }}</span>@endif
                                            @if ($day->ot_approved_minutes)
                                                <span class="text-green-700 dark:text-green-300">{{ __('OT :h', ['h' => AttendanceDay::hoursLabel($day->ot_approved_minutes)]) }}</span>
                                            @elseif ($day->ot_minutes)
                                                <span class="text-zinc-500">{{ __('OT :h?', ['h' => AttendanceDay::hoursLabel($day->ot_minutes)]) }}</span>
                                            @endif
                                        </div>
                                    @elseif (! $cell['holiday'])
                                        <div class="text-zinc-400">
                                            {{ $cell['plannedType'] === DayType::Weekend ? __('Weekend') : ($cell['plannedShift'] ?? '') }}
                                        </div>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>

            <div class="flex flex-wrap gap-x-6 gap-y-1 px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                <span>{{ __('Present: :n', ['n' => $totals['present']]) }}</span>
                <span>{{ __('Absent: :n', ['n' => $totals['absent']]) }}</span>
                <span>{{ __('Late: :n', ['n' => $totals['late']]) }}</span>
                <span>{{ __('Leave: :n', ['n' => $totals['leave']]) }}</span>
                <span>{{ __('Overtime worked: :h', ['h' => AttendanceDay::hoursLabel($totals['overtime'])]) }}</span>
                <span>{{ __('Overtime approved: :h', ['h' => AttendanceDay::hoursLabel($totals['approvedOvertime'])]) }}</span>
            </div>
        </x-ui.card>
    @endif
</div>
