@php $days = fn (?float $d) => $d === null ? '—' : trans_choice(':count day|:count days', (int) round($d), ['count' => rtrim(rtrim(number_format($d, 1), '0'), '.')]); @endphp
<div class="space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <flux:field>
            <flux:label>{{ __('Hired in') }}</flux:label>
            <flux:select wire:model.live="months">
                <flux:select.option value="">{{ __('All time') }}</flux:select.option>
                @foreach ($periods as $optionValue => $optionLabel)
                    <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="months" />
        </flux:field>
        <flux:button icon="arrow-down-tray" wire:click="export">{{ __('Export') }}</flux:button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="glass rounded-2xl p-5">
            <div class="flex items-center justify-between gap-4">
                <flux:text class="font-medium">{{ __('Hires') }}</flux:text>
                <div class="flex size-9 items-center justify-center rounded-xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                    <flux:icon name="user-plus" variant="mini" />
                </div>
            </div>
            <div class="mt-2 text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ $toHire['count'] }}</div>
        </div>

        <div class="glass rounded-2xl p-5">
            <div class="flex items-center justify-between gap-4">
                <flux:text class="font-medium">{{ __('Time to hire (average)') }}</flux:text>
                <div class="flex size-9 items-center justify-center rounded-xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                    <flux:icon name="clock" variant="mini" />
                </div>
            </div>
            <div class="mt-2 text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ $days($toHire['average']) }}</div>
            <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Median :d', ['d' => $days($toHire['median'])]) }}</div>
        </div>

        <div class="glass rounded-2xl p-5">
            <div class="flex items-center justify-between gap-4">
                <flux:text class="font-medium">{{ __('Time to fill (average)') }}</flux:text>
                <div class="flex size-9 items-center justify-center rounded-xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                    <flux:icon name="calendar-days" variant="mini" />
                </div>
            </div>
            <div class="mt-2 text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ $days($toFill['average']) }}</div>
            <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Median :d', ['d' => $days($toFill['median'])]) }}</div>
        </div>

        <div class="glass rounded-2xl p-5">
            <div class="flex items-center justify-between gap-4">
                <flux:text class="font-medium">{{ __('Entities') }}</flux:text>
                <div class="flex size-9 items-center justify-center rounded-xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                    <flux:icon name="building-office" variant="mini" />
                </div>
            </div>
            <div class="mt-2 text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ $byUnit->count() }}</div>
        </div>
    </div>
    <flux:text class="text-xs">
        {{ __('Time to hire: from applying to "Hire". Time to fill: from the manpower request\'s approval to "Hire".') }}
    </flux:text>

    @if ($hires->isEmpty())
        <div class="glass overflow-hidden rounded-2xl">
            <div class="p-6">
                <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
                    <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                        <flux:icon name="user-plus" />
                    </div>
                    <flux:heading size="lg" class="mt-4">{{ __('No hires in this period') }}</flux:heading>
                </div>
            </div>
        </div>
    @else
        <div class="glass overflow-hidden rounded-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
                <div>
                    <flux:heading size="lg">{{ __('Per entity') }}</flux:heading>
                </div>
            </div>

            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    <tr class="border-b border-white/50 dark:border-white/10">
                        <th class="px-6 py-3 font-medium">{{ __('Entity') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ __('Hires') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ __('Time to hire') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ __('Time to fill') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($byUnit as $row)
                        <tr class="border-b border-white/50 last:border-0 dark:border-white/10">
                            <td class="px-6 py-3 font-medium">{{ $row['unit'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $row['hire']['count'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $days($row['hire']['average']) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $days($row['fill']['average']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="glass overflow-hidden rounded-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
                <div>
                    <flux:heading size="lg">{{ __('Hires') }}</flux:heading>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        <tr class="border-b border-white/50 dark:border-white/10">
                            <th class="px-6 py-3 font-medium">{{ __('Applicant') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Posting') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Applied') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Hired') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('Time to hire') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('Time to fill') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($hires as $hire)
                            @php $application = $hire['application']; @endphp
                            <tr class="border-b border-white/50 last:border-0 dark:border-white/10" wire:key="hire-{{ $application->id }}">
                                <td class="px-6 py-3">
                                    @if ($application->hiredEmployee)
                                        <a href="{{ route('hrm.employees.show', $application->hired_employee_id) }}" wire:navigate class="font-medium text-brand-800 hover:underline dark:text-brand-200">{{ $application->applicant->name }}</a>
                                        <div class="text-xs text-zinc-500">{{ $application->hiredEmployee->code }}</div>
                                    @else
                                        <span class="font-medium">{{ $application->applicant->name }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    {{ $application->jobPosting->title }}
                                    <div class="text-xs text-zinc-500">{{ $application->jobPosting->number() }} · {{ $application->jobPosting->manpowerRequest->entity?->name }}</div>
                                </td>
                                <td class="px-4 py-3 tabular-nums">{{ format_date($application->applied_on) }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ format_date($hire['hired_at']) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $days($hire['days_to_hire']) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $days($hire['days_to_fill']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
