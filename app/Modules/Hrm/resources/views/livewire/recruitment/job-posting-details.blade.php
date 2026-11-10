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
        </div>
    </div>
</div>
