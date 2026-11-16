@php use App\Modules\Hrm\Enums\InterviewStatus; @endphp
<div class="grid gap-4 lg:grid-cols-3">
    {{-- Interviews --}}
    <div class="glass overflow-hidden rounded-2xl lg:col-span-2">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ __('Interviews') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Plan a round with the calendar button in the pipeline.') }}</flux:text>
            </div>
        </div>

        @if ($interviews->isEmpty())
            <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                    <flux:icon name="calendar-days" />
                </div>
                <flux:heading size="lg" class="mt-4">{{ __('No interviews yet') }}</flux:heading>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        <tr class="border-b border-white/50 dark:border-white/10">
                            <th class="px-6 py-3 font-medium">{{ __('Applicant') }}</th>
                            <th class="px-6 py-3 font-medium">{{ __('When') }}</th>
                            <th class="px-6 py-3 font-medium">{{ __('Panel') }}</th>
                            <th class="px-6 py-3 text-right font-medium">{{ __('Result') }}</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($interviews as $interview)
                            <tr class="border-b border-white/50 align-top last:border-0 dark:border-white/10" wire:key="interview-{{ $interview->id }}">
                                <td class="px-6 py-3">
                                    <span class="font-medium">{{ $interview->application->applicant->name }}</span>
                                    <div class="mt-1 flex items-center gap-1">
                                        <span class="text-xs text-zinc-500">{{ $interview->title() }}</span>
                                        <x-ui.badge :color="$interview->status->color()">{{ $interview->status->label() }}</x-ui.badge>
                                    </div>
                                </td>
                                <td class="px-6 py-3">
                                    <div class="tabular-nums">{{ format_datetime($interview->scheduled_at) }}</div>
                                    <div class="text-xs text-zinc-500">{{ $interview->location ?? '—' }}</div>
                                </td>
                                <td class="px-6 py-3">
                                    {{-- ✓ = scores sent. A name opens that panelist's sheet (view, or enter it on their behalf). --}}
                                    <ul class="space-y-0.5">
                                        @foreach ($interview->panelists as $panelist)
                                            <li wire:key="panelist-{{ $panelist->id }}">
                                                <button type="button" x-on:click="$dispatch('open-score-sheet', { panelistId: {{ $panelist->id }} })"
                                                    class="inline-flex items-center gap-1 text-left text-brand-800 hover:underline dark:text-brand-200">
                                                    @if ($panelist->isSubmitted())
                                                        <flux:icon.check-circle variant="micro" class="text-green-600" />
                                                    @else
                                                        <flux:icon.clock variant="micro" class="text-zinc-400" />
                                                    @endif
                                                    {{ $panelist->employee?->name }}
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                </td>
                                <td class="px-6 py-3 text-right font-medium tabular-nums">{{ $results[$interview->id] !== null ? $results[$interview->id].'%' : '—' }}</td>
                                <td class="px-6 py-3 text-right">
                                    @if ($interview->status === InterviewStatus::Scheduled)
                                        <div class="flex justify-end gap-1">
                                            <flux:button size="sm" variant="ghost" icon="pencil-square" :tooltip="__('Change')"
                                                x-on:click="$dispatch('edit-interview', { id: {{ $interview->id }} })" />
                                            <flux:button size="sm" variant="ghost" icon="x-mark" :tooltip="__('Cancel')" x-on:click="$dispatch('confirm', {
                                                title: @js(__('Cancel this interview?')),
                                                message: @js(__('The round keeps its number. Scores already sent no longer count.')),
                                                confirmText: @js(__('Cancel interview')),
                                                onConfirm: () => $wire.cancel({{ $interview->id }}),
                                            })" />
                                            <flux:button size="sm" icon="check" wire:click="complete({{ $interview->id }})">{{ __('Complete') }}</flux:button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Ranking --}}
    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ __('Ranking') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Average of completed rounds') }}</flux:text>
            </div>
        </div>

        @if ($ranking->isEmpty())
            <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                    <flux:icon name="trophy" />
                </div>
                <flux:heading size="lg" class="mt-4">{{ __('No results yet') }}</flux:heading>
                <flux:text class="mt-1 max-w-sm">{{ __('Applicants appear here once a round is completed.') }}</flux:text>
            </div>
        @else
            <ol class="divide-y divide-white/50 dark:divide-white/10">
                @foreach ($ranking as $row)
                    <li class="flex items-center gap-3 px-6 py-3 text-sm" wire:key="rank-{{ $row['application']->id }}">
                        <span class="grid size-7 shrink-0 place-items-center rounded-full bg-brand-500/10 font-semibold tabular-nums">{{ $row['rank'] }}</span>
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('hrm.recruitment.applicants.show', $row['application']->applicant_id) }}" wire:navigate class="font-medium text-brand-800 hover:underline dark:text-brand-200">{{ $row['application']->applicant->name }}</a>
                            <div class="text-xs text-zinc-500">{{ $row['application']->stage->label() }}</div>
                        </div>
                        <span class="font-semibold tabular-nums">{{ $row['score'] }}%</span>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</div>
