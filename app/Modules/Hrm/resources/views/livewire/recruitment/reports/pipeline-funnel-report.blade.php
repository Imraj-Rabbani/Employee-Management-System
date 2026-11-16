@php use App\Modules\Hrm\Services\RecruitmentStats; @endphp
<div class="space-y-4">
    {{-- Overall funnel: one bar per step, width = share of everyone who applied. --}}
    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ __('All postings below') }}</flux:heading>
            </div>
        </div>

        <div class="p-6">
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
        </div>
    </div>

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="flex flex-wrap gap-3">
            <flux:field>
                <flux:label>{{ __('Posting status') }}</flux:label>
                <flux:select wire:model.live="status">
                    <flux:select.option value="">{{ __('All') }}</flux:select.option>
                    @foreach ($statuses as $optionValue => $optionLabel)
                        <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="status" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Org unit') }}</flux:label>
                <flux:select wire:model.live="orgUnit">
                    <flux:select.option value="">{{ __('All') }}</flux:select.option>
                    @foreach ($units as $optionValue => $optionLabel)
                        <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="orgUnit" />
            </flux:field>
        </div>
        <flux:button icon="arrow-down-tray" wire:click="export">{{ __('Export') }}</flux:button>
    </div>

    <div class="glass overflow-hidden rounded-2xl">
        @if ($rows->isEmpty())
            <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                    <flux:icon name="funnel" />
                </div>
                <flux:heading size="lg" class="mt-4">{{ __('No job postings') }}</flux:heading>
            </div>
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
    </div>
</div>
