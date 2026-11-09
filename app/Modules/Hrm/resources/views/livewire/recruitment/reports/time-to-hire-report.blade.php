@php $days = fn (?float $d) => $d === null ? '—' : trans_choice(':count day|:count days', (int) round($d), ['count' => rtrim(rtrim(number_format($d, 1), '0'), '.')]); @endphp
<div class="space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <x-form.select wire:model.live="months" :label="__('Hired in')" :options="$periods" :placeholder="__('All time')" />
        <flux:button icon="arrow-down-tray" wire:click="export">{{ __('Export') }}</flux:button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat :label="__('Hires')" :value="$toHire['count']" icon="user-plus" />
        <x-ui.stat :label="__('Time to hire (average)')" :value="$days($toHire['average'])" icon="clock" :trend="__('Median :d', ['d' => $days($toHire['median'])])" />
        <x-ui.stat :label="__('Time to fill (average)')" :value="$days($toFill['average'])" icon="calendar-days" :trend="__('Median :d', ['d' => $days($toFill['median'])])" />
        <x-ui.stat :label="__('Org units')" :value="$byUnit->count()" icon="building-office" />
    </div>
    <flux:text class="text-xs">
        {{ __('Time to hire: from applying to "Hire". Time to fill: from the manpower request\'s approval to "Hire".') }}
    </flux:text>

    @if ($hires->isEmpty())
        <x-ui.card><x-ui.empty icon="user-plus" :heading="__('No hires in this period')" /></x-ui.card>
    @else
        <x-ui.card :heading="__('Per org unit')" :padding="false">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    <tr class="border-b border-white/50 dark:border-white/10">
                        <th class="px-6 py-3 font-medium">{{ __('Org unit') }}</th>
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
        </x-ui.card>

        <x-ui.card :heading="__('Hires')" :padding="false">
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
                                    <div class="text-xs text-zinc-500">{{ $application->jobPosting->number() }} · {{ $application->jobPosting->manpowerRequest->orgUnit?->name }}</div>
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
        </x-ui.card>
    @endif
</div>
