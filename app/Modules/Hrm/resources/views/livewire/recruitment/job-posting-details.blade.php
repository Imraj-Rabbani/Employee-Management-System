@php
    use App\Modules\Hrm\Enums\JobPostingStatus;
    $request = $posting->manpowerRequest;
@endphp
<div class="space-y-4">
    <x-ui.card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <flux:heading size="xl">{{ $posting->title }}</flux:heading>
                    <x-ui.badge :color="$posting->status->color()">{{ $posting->status->label() }}</x-ui.badge>
                </div>
                <flux:text class="mt-1">
                    {{ $posting->number() }} · {{ $request->orgUnit?->name }} ·
                    {{ trans_choice(':count opening|:count openings', $posting->openings, ['count' => $posting->openings]) }}
                </flux:text>
                <flux:text class="mt-1 text-xs">{{ $posting->applicationWindow() }}</flux:text>
            </div>

            <div class="flex flex-wrap gap-2">
                @if ($posting->status !== JobPostingStatus::Closed)
                    <flux:button icon="pencil-square" x-on:click="$dispatch('edit-job-posting', { id: {{ $posting->id }} })">{{ __('Edit') }}</flux:button>
                @endif

                @if ($posting->status === JobPostingStatus::Open)
                    <flux:button icon="lock-closed" x-on:click="$dispatch('confirm', {
                        title: @js(__('Close this posting?')),
                        message: @js(__('No new applicants can be added. Applicants already in the pipeline carry on.')),
                        confirmText: @js(__('Close')),
                        onConfirm: () => $wire.close(),
                    })">{{ __('Close') }}</flux:button>
                @else
                    <flux:button variant="primary" icon="megaphone" wire:click="open">
                        {{ $posting->status === JobPostingStatus::Closed ? __('Reopen') : __('Open for applications') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </x-ui.card>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-ui.card class="space-y-6 lg:col-span-2">
            <x-ui.details :items="[
                __('Opens') => format_date($posting->open_date),
                __('Closes') => $posting->close_date ? format_date($posting->close_date) : __('Until closed'),
                __('Openings') => $posting->openings,
            ]" />

            <div>
                <flux:heading class="mb-1">{{ __('Description') }}</flux:heading>
                <flux:text class="whitespace-pre-line">{{ $posting->description ?: '—' }}</flux:text>
            </div>
            <div>
                <flux:heading class="mb-1">{{ __('Requirements') }}</flux:heading>
                <flux:text class="whitespace-pre-line">{{ $posting->requirements ?: '—' }}</flux:text>
            </div>
        </x-ui.card>

        <div class="space-y-4">
            <x-ui.card>
                <div class="mb-4 flex items-center justify-between gap-2">
                    <flux:heading>{{ __('Manpower request') }}</flux:heading>
                    <flux:link :href="route('hrm.recruitment.requests.show', $request->id)" wire:navigate class="text-sm">{{ $request->number() }}</flux:link>
                </div>
                <x-ui.details class="!grid-cols-1" :items="[
                    __('For') => $request->headcount.' × '.($request->designation?->name ?? '?'),
                    __('Grade') => $request->grade?->name,
                    __('Salary (monthly gross)') => $request->salaryRange(),
                    __('Still to advertise') => $request->openingsToAdvertise(),
                ]" />
            </x-ui.card>

            {{-- Applicants (6.4). "Add applicant" opens the applicant form with this posting chosen. --}}
            <x-ui.card :heading="__('Applicants')" :subheading="trans_choice(':count application|:count applications', $applications->count(), ['count' => $applications->count()])" :padding="false">
                @if ($posting->isAcceptingApplications())
                    <x-slot:actions>
                        <flux:button size="sm" icon="plus" x-on:click="$dispatch('create-applicant', { postingId: {{ $posting->id }} })">{{ __('Add applicant') }}</flux:button>
                    </x-slot:actions>
                @endif

                @if ($applications->isEmpty())
                    <x-ui.empty icon="users" :heading="__('No applicants yet')"
                        :description="$posting->isAcceptingApplications() ? null : __('Applicants can be added while the posting is open and inside its dates.')" />
                @else
                    <ul class="divide-y divide-white/50 dark:divide-white/10">
                        @foreach ($applications as $application)
                            <li class="flex items-center justify-between gap-2 px-6 py-3 text-sm" wire:key="app-{{ $application->id }}">
                                <div class="min-w-0">
                                    <a href="{{ route('hrm.recruitment.applicants.show', $application->applicant_id) }}" wire:navigate class="font-medium text-brand-800 hover:underline dark:text-brand-200">{{ $application->applicant->name }}</a>
                                    <div class="text-xs text-zinc-500">{{ format_date($application->applied_on) }}</div>
                                </div>
                                <x-ui.badge :color="$application->stage->color()">{{ $application->stage->label() }}</x-ui.badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>
    </div>
</div>
