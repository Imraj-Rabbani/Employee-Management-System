<div class="space-y-4">
    <x-ui.card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <flux:heading size="xl">{{ $request->number() }}</flux:heading>
                    @if ($status)
                        <x-ui.badge :color="$status->color()">{{ $status->label() }}</x-ui.badge>
                    @else
                        <x-ui.badge>{{ __('Draft') }}</x-ui.badge>
                    @endif
                </div>
                <flux:text class="mt-1">
                    {{ $request->headcount }} × {{ $request->designation?->name }} · {{ $request->orgUnit?->name }}
                </flux:text>
                <flux:text class="mt-1 text-xs">{{ __('Created :date', ['date' => format_date($request->created_at)]) }}</flux:text>
            </div>

            @if ($request->isEditable())
                <div class="flex gap-2">
                    <flux:button icon="pencil-square" x-on:click="$dispatch('edit-manpower-request', { id: {{ $request->id }} })">{{ __('Edit') }}</flux:button>
                    <flux:button variant="primary" icon="paper-airplane" x-on:click="$dispatch('confirm', {
                        title: @js(__('Send for approval?')),
                        message: @js(__('You cannot change the request while it is waiting for approval.')),
                        confirmText: @js(__('Send')),
                        onConfirm: () => $wire.submit(),
                    })">{{ $status ? __('Send again') : __('Send for approval') }}</flux:button>
                </div>
            @endif
        </div>
    </x-ui.card>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2">
            <flux:heading class="mb-4">{{ __('Details') }}</flux:heading>
            @include('hrm::approvals.manpower-request', ['document' => $request])
        </x-ui.card>

        <x-ui.card>
            <flux:heading class="mb-4">{{ __('Approval') }}</flux:heading>
            @if ($status)
                <x-approval-timeline :document="$request" />
            @else
                <flux:text>{{ __('Not sent yet. Check the details, then press "Send for approval".') }}</flux:text>
            @endif
        </x-ui.card>
    </div>
</div>
