@php use App\Modules\Hrm\Enums\ApplicationStage; @endphp
<div>
    <x-ui.card :heading="__('Pipeline')" :subheading="trans_choice(':count application|:count applications', $counts['all'], ['count' => $counts['all']])" :padding="false">
        @if ($posting->isAcceptingApplications())
            <x-slot:actions>
                <flux:button size="sm" icon="plus" x-on:click="$dispatch('create-applicant', { postingId: {{ $posting->id }} })">{{ __('Add applicant') }}</flux:button>
            </x-slot:actions>
        @endif

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
            <x-ui.empty icon="users" :heading="__('Nobody here')"
                :description="$counts['all'] === 0 && ! $posting->isAcceptingApplications() ? __('Applicants can be added while the posting is open and inside its dates.') : null" />
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
                                    @php $planned = $application->interviews->firstWhere('status', \App\Modules\Hrm\Enums\InterviewStatus::Scheduled); @endphp
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
    </x-ui.card>

    {{-- Move modal --}}
    <x-ui.modal name="application-move" :heading="$moving ? __('Move :name', ['name' => $moving->applicant->name]) : __('Move')"
        :subheading="$moving ? __('Now: :stage', ['stage' => $moving->stage->label()]) : null">
        <form wire:submit="move" id="application-move" class="space-y-4">
            <x-form.select wire:model.live="to" :label="__('Move to')" :options="$targets" :placeholder="__('Choose…')" required
                :hint="__('Offered and Hired are set by the offer and the hire.')" />
            @if ($needsReason)
                <x-form.select wire:model="reasonId" :label="__('Reason')" :options="$reasons" :placeholder="$reasons ? __('Choose…') : __('Add reasons in Recruitment setup first')" required />
            @endif
            <x-form.textarea wire:model="note" :label="__('Note')" :hint="__('Kept in the application\'s history')" rows="3" />
        </form>
        <x-slot:footer>
            <flux:button type="submit" form="application-move" variant="primary">{{ __('Move') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
