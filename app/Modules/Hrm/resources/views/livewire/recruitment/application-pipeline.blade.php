@php use App\Modules\Hrm\Enums\ApplicationStage; @endphp
<div>
    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ __('Pipeline') }}</flux:heading>
                <flux:text class="mt-1">{{ trans_choice(':count application|:count applications', $counts['all'], ['count' => $counts['all']]) }}</flux:text>
            </div>
            @if ($posting->isAcceptingApplications())
                <div class="flex shrink-0 items-center gap-2">
                    <flux:button size="sm" icon="plus" x-on:click="$dispatch('create-applicant', { postingId: {{ $posting->id }} })">{{ __('Add applicant') }}</flux:button>
                </div>
            @endif
        </div>

        {{-- Tabs: "Open" (still in the running), each stage, "All". The count is shown on each. --}}
        <nav class="flex flex-wrap gap-1 border-b border-white/50 px-4 dark:border-white/10" aria-label="{{ __('Stages') }}">
            @foreach (['open' => __('In progress'), ...ApplicationStage::options(), 'all' => __('All')] as $key => $label)
                <button type="button" wire:click="$set('tab', '{{ $key }}')" @class([
                    '-mb-px border-b-2 px-3 py-2 text-sm font-medium transition',
                    'border-brand-800 text-brand-900 dark:border-white dark:text-white' => $tab === $key,
                    'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $tab !== $key,
                ]) @if ($tab === $key) aria-current="true" @endif>
                    {{ $label }} <span class="ml-1 rounded-full bg-zinc-500/10 px-1.5 text-xs tabular-nums">{{ $counts[$key] }}</span>
                </button>
            @endforeach
        </nav>

        @if ($rows->isEmpty())
            <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                    <flux:icon name="users" />
                </div>
                <flux:heading size="lg" class="mt-4">{{ __('Nobody here') }}</flux:heading>
                @if ($counts['all'] === 0 && ! $posting->isAcceptingApplications())
                    <flux:text class="mt-1 max-w-sm">{{ __('Applicants can be added while the posting is open and inside its dates.') }}</flux:text>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        <tr class="border-b border-white/50 dark:border-white/10">
                            <th class="px-6 py-3 font-medium">{{ __('Applicant') }}</th>
                            <th class="px-6 py-3 font-medium">{{ __('Current job') }}</th>
                            <th class="px-6 py-3 text-right font-medium">{{ __('Expected salary') }}</th>
                            <th class="px-6 py-3 font-medium">{{ __('Applied') }}</th>
                            <th class="px-6 py-3 font-medium">{{ __('Stage') }}</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $application)
                            <tr class="border-b border-white/50 last:border-0 dark:border-white/10" wire:key="pipe-{{ $application->id }}">
                                <td class="px-6 py-3">
                                    <a href="{{ route('hrm.recruitment.applicants.show', $application->applicant_id) }}" wire:navigate class="font-medium text-brand-800 hover:underline dark:text-brand-200">{{ $application->applicant->name }}</a>
                                    <div class="text-xs text-zinc-500">{{ $application->applicant->phone ?? $application->applicant->email }}</div>
                                </td>
                                <td class="px-6 py-3">{{ collect([$application->applicant->current_designation, $application->applicant->current_employer])->filter()->join(' · ') ?: '—' }}</td>
                                <td class="px-6 py-3 text-right tabular-nums">{{ $application->applicant->expected_salary !== null ? number_format((float) $application->applicant->expected_salary) : '—' }}</td>
                                <td class="px-6 py-3 tabular-nums">{{ format_date($application->applied_on) }}</td>
                                <td class="px-6 py-3">
                                    <x-ui.badge :color="$application->stage->color()">{{ $application->stage->label() }}</x-ui.badge>
                                    @php
                                        $planned = $application->interviews->firstWhere('status', \App\Modules\Hrm\Enums\InterviewStatus::Scheduled);
                                        $offer = $application->offers->first(fn ($o) => ! $o->status()->isDead());
                                    @endphp
                                    @if ($offer)
                                        <a href="{{ route('hrm.recruitment.offers.show', $offer->id) }}" wire:navigate class="ml-1 text-xs text-brand-800 hover:underline dark:text-brand-200">{{ $offer->number() }} · {{ $offer->status()->label() }}</a>
                                    @endif
                                    <div class="mt-1 text-xs text-zinc-500">
                                        @if ($planned)
                                            {{ $planned->title() }} · {{ format_datetime($planned->scheduled_at) }}
                                        @elseif ($application->rejectionReason)
                                            {{ $application->rejectionReason->name }}
                                        @elseif ($application->stage_changed_at)
                                            {{ __('since :when', ['when' => $application->stage_changed_at->diffForHumans()]) }}
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <div class="flex justify-end gap-1">
                                        <flux:button size="sm" variant="ghost" icon="clock" :tooltip="__('History')"
                                            x-on:click="$dispatch('show-application-history', { id: {{ $application->id }} })" />
                                        @if (! $application->stage->isFinal() && ! $planned)
                                            <flux:button size="sm" variant="ghost" icon="calendar-days" :tooltip="__('Schedule interview')"
                                                x-on:click="$dispatch('schedule-interview', { applicationId: {{ $application->id }} })" />
                                        @endif
                                        @if (! $offer && ! $application->stage->isFinal() && (int) $application->stage->step() >= 2)
                                            <flux:button size="sm" variant="ghost" icon="banknotes" :tooltip="__('Make offer')"
                                                x-on:click="$dispatch('create-offer', { applicationId: {{ $application->id }} })" />
                                        @endif
                                        @if ($canMove($application))
                                            <flux:button size="sm" icon="arrow-right" wire:click="startMove({{ $application->id }})">{{ __('Move') }}</flux:button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Move modal --}}
    <x-ui.modal name="application-move" :heading="$moving ? __('Move :name', ['name' => $moving->applicant->name]) : __('Move')"
        :subheading="$moving ? __('Now: :stage', ['stage' => $moving->stage->label()]) : null">
        <form wire:submit="move" id="application-move" class="space-y-4">
            <flux:field>
                <flux:label>{{ __('Move to') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:description>{{ __('Offered and Hired are set by the offer and the hire.') }}</flux:description>
                <flux:select aria-required="true" wire:model.live="to">
                    <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                    @foreach ($targets as $optionValue => $optionLabel)
                        <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="to" />
            </flux:field>
            @if ($needsReason)
                <flux:field>
                    <flux:label>{{ __('Reason') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:select aria-required="true" wire:model="reasonId">
                        <flux:select.option value="">{{ $reasons ? __('Choose…') : __('Add reasons in Recruitment setup first') }}</flux:select.option>
                        @foreach ($reasons as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="reasonId" />
                </flux:field>
            @endif
            <flux:field>
                <flux:label>{{ __('Note') }}</flux:label>
                <flux:description>{{ __('Kept in the application\'s history') }}</flux:description>
                <flux:textarea wire:model="note" rows="3" />
                <flux:error name="note" />
            </flux:field>
        </form>
        <x-slot:footer>
            <flux:button type="submit" form="application-move" variant="primary">{{ __('Move') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
