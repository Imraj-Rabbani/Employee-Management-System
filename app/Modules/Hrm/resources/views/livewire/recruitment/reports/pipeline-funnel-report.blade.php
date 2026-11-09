@php use App\Modules\Hrm\Services\RecruitmentStats; @endphp
<div class="space-y-4">
    {{-- Overall funnel: one bar per step, width = share of everyone who applied. --}}
    <x-ui.card :heading="__('All postings below')">
        <div class="space-y-2">
            @foreach ($steps as $i => $stage)
                @php $count = $totals['reached'][$i + 1]; $width = $totals['reached'][1] ? max(2, $count / $totals['reached'][1] * 100) : 0; @endphp
                <div class="grid grid-cols-[8rem_1fr_5rem] items-center gap-3 text-sm">
                    <span class="text-zinc-500">{{ $stage->label() }}</span>
                    <div class="h-6 rounded-md bg-zinc-500/10">
                        <div class="h-6 rounded-md bg-brand-800/80 dark:bg-brand-300/70" style="width: {{ $width }}%"></div>
                    </div>
                    <span class="text-right font-medium tabular-nums">{{ $count }} <span class="text-xs text-zinc-500">{{ RecruitmentStats::percent($count, $totals['reached'][1]) }}</span></span>
                </div>
            @endforeach
            <flux:text class="pt-2 text-xs">
                {{ __('Rejected: :r · Withdrew: :w. "Reached" counts everyone who got to a step, also if they were rejected later.', ['r' => $totals['rejected'], 'w' => $totals['withdrawn']]) }}
            </flux:text>
        </div>
    </x-ui.card>

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="flex flex-wrap gap-3">
            <x-form.select wire:model.live="status" :label="__('Posting status')" :options="$statuses" :placeholder="__('All')" />
            <x-form.select wire:model.live="orgUnit" :label="__('Org unit')" :options="$units" :placeholder="__('All')" />
        </div>
        <flux:button icon="arrow-down-tray" wire:click="export">{{ __('Export') }}</flux:button>
    </div>

    <x-ui.card :padding="false">
        @if ($rows->isEmpty())
            <x-ui.empty icon="funnel" :heading="__('No job postings')" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        <tr class="border-b border-white/50 dark:border-white/10">
                            <th class="px-6 py-3 font-medium">{{ __('Posting') }}</th>
                            @foreach ($steps as $stage)
                                <th class="px-4 py-3 text-right font-medium">{{ $stage->label() }}</th>
                            @endforeach
                            <th class="px-4 py-3 text-right font-medium">{{ __('Rejected') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('Withdrawn') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-b border-white/50 last:border-0 dark:border-white/10" wire:key="funnel-{{ $row['posting']->id }}">
                                <td class="px-6 py-3">
                                    <a href="{{ route('hrm.recruitment.postings.show', $row['posting']->id) }}" wire:navigate class="font-medium text-brand-800 hover:underline dark:text-brand-200">{{ $row['posting']->title }}</a>
                                    <div class="text-xs text-zinc-500">{{ $row['posting']->number() }} · {{ $row['posting']->manpowerRequest->orgUnit?->name }} · {{ $row['posting']->status->label() }}</div>
                                </td>
                                @foreach ($row['reached'] as $step => $count)
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        {{ $count }}
                                        @if ($step > 1)
                                            <div class="text-xs text-zinc-500">{{ RecruitmentStats::percent($count, $row['reached'][1]) }}</div>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-4 py-3 text-right tabular-nums">{{ $row['rejected'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $row['withdrawn'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>
</div>
