{{-- A team's month (TeamCalendar, 7.8): employees × days, one letter per processed day. --}}
@php
    use App\Modules\Payroll\Services\AttendanceCalendar;
    $cellColor = [
        'success' => 'bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-200',
        'warning' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-200',
        'danger' => 'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-200',
        'info' => 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-200',
        'neutral' => 'bg-zinc-100 text-zinc-500 dark:bg-white/10 dark:text-zinc-400',
    ];
@endphp
<div class="space-y-4">
    <div class="flex flex-wrap items-end gap-3">
        <div class="flex items-center gap-1">
            <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="previous" :aria-label="__('Previous month')" />
            <span class="min-w-32 text-center text-sm font-medium">{{ $monthStart->translatedFormat('F Y') }}</span>
            <flux:button size="sm" variant="ghost" icon="chevron-right" wire:click="next" :aria-label="__('Next month')" />
        </div>
        @if ($mode === 'hr')
            <div class="w-64"><x-form.select wire:model.live="orgUnitId" :options="$units" :placeholder="__('All org units')" /></div>
        @endif
        <div class="w-56"><flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Name or code')" size="sm" /></div>
    </div>

    @if ($tooMany)
        <flux:callout variant="warning" icon="information-circle" :heading="__('Only the first :n employees are shown. Choose an org unit or search to see the others.', ['n' => \App\Modules\Payroll\Livewire\Attendance\TeamCalendar::MAX_ROWS])" />
    @endif

    <x-ui.card :padding="false">
        @if ($employees->isEmpty())
            <x-ui.empty icon="users" :heading="$mode === 'team' ? __('Nobody reports to you') : __('No employees')" />
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
                            <tr wire:key="team-{{ $employee->id }}" class="border-t border-zinc-100 dark:border-white/5">
                                <td class="sticky left-0 bg-white px-3 py-1 whitespace-nowrap dark:bg-zinc-900">
                                    <a href="{{ $this->employeeUrl($employee->id) }}" wire:navigate class="text-brand-800 hover:underline dark:text-brand-200">{{ $employee->code }} · {{ $employee->name }}</a>
                                </td>
                                @foreach ($dates as $date)
                                    @php $day = $matrix[$employee->id][$date->toDateString()] ?? null; @endphp
                                    <td class="p-0.5 text-center">
                                        @if ($day)
                                            <span @class(['relative inline-flex size-6 items-center justify-center rounded font-semibold', $cellColor[$day->status->color()]])
                                                  title="{{ format_date($date) }}: {{ $day->status->label() }} {{ $day->in_at?->format('H:i') }}{{ $day->out_at ? '–'.$day->out_at->format('H:i') : '' }}{{ $day->is_late ? ' · '.__('Late') : '' }}{{ $day->is_corrected ? ' · '.__('Corrected') : '' }}">
                                                {{ AttendanceCalendar::letter($day->status) }}
                                                @if ($day->is_late)<span class="absolute -right-0.5 -top-0.5 size-1.5 rounded-full bg-amber-500"></span>@endif
                                            </span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    <flux:text class="text-xs">
        {{ __('P present · ½ half day · A absent · L leave · O off · H holiday · orange dot = late. Hover a day for its times. Empty = not processed yet.') }}
    </flux:text>
</div>
