@php use App\Modules\Hrm\Enums\InterviewStatus; @endphp
<div>
    @if ($employee === null)
        <x-ui.card>
            <x-ui.empty icon="user" :heading="__('Your login is not linked to an employee record')" :description="__('Ask HR to link it.')" />
        </x-ui.card>
    @else
        <x-ui.card :padding="false">
            @if ($seats->isEmpty())
                <x-ui.empty icon="calendar-days" :heading="__('No interviews')" :description="__('When HR puts you on an interview panel, it appears here and you get an email.')" />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            <tr class="border-b border-white/50 dark:border-white/10">
                                <th class="px-6 py-3 font-medium">{{ __('When') }}</th>
                                <th class="px-6 py-3 font-medium">{{ __('Applicant') }}</th>
                                <th class="px-6 py-3 font-medium">{{ __('Job') }}</th>
                                <th class="px-6 py-3 font-medium">{{ __('Where') }}</th>
                                <th class="px-6 py-3 font-medium">{{ __('My score sheet') }}</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($seats as $seat)
                                @php $interview = $seat->interview; @endphp
                                <tr class="border-b border-white/50 last:border-0 dark:border-white/10" wire:key="seat-{{ $seat->id }}">
                                    <td class="px-6 py-3">
                                        <div class="font-medium tabular-nums">{{ format_datetime($interview->scheduled_at) }}</div>
                                        <div class="text-xs text-zinc-500">{{ $interview->title() }} · {{ $interview->duration_minutes }} {{ __('min') }}</div>
                                    </td>
                                    <td class="px-6 py-3">
                                        {{ $interview->application->applicant->name }}
                                        @if ($interview->application->applicant->cv_path)
                                            <a href="{{ route('ess.interviews.cv', $seat) }}" class="ml-1 inline-flex items-center gap-0.5 text-xs text-brand-800 hover:underline dark:text-brand-200"><flux:icon.paper-clip variant="micro" />{{ __('CV') }}</a>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3">{{ $interview->application->jobPosting->title }}</td>
                                    <td class="px-6 py-3">{{ $interview->location ?? '—' }}</td>
                                    <td class="px-6 py-3">
                                        @if ($interview->status === InterviewStatus::Cancelled)
                                            <x-ui.badge>{{ __('Interview cancelled') }}</x-ui.badge>
                                        @elseif ($seat->isSubmitted())
                                            <x-ui.badge color="success">{{ __('Sent') }}</x-ui.badge>
                                        @elseif ($seat->scores->isNotEmpty())
                                            <x-ui.badge color="warning">{{ __('Draft') }}</x-ui.badge>
                                        @else
                                            <x-ui.badge color="info">{{ __('To do') }}</x-ui.badge>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        @if ($interview->status !== InterviewStatus::Cancelled)
                                            <flux:button size="sm" :variant="$seat->isSubmitted() || $interview->status !== InterviewStatus::Scheduled ? 'ghost' : 'primary'"
                                                x-on:click="$dispatch('open-score-sheet', { panelistId: {{ $seat->id }} })">
                                                {{ $seat->isSubmitted() || $interview->status !== InterviewStatus::Scheduled ? __('View') : __('Score') }}
                                            </flux:button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    @endif
</div>
