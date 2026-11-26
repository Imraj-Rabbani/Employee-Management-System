<div class="space-y-4">
    <div class="glass overflow-hidden rounded-2xl">
        <div class="p-6">
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
                        {{ $request->headcount }} × {{ $request->designation?->name }} · {{ $request->entity?->name }}
                    </flux:text>
                    <flux:text class="mt-1 text-xs">{{ __('Created :date', ['date' => format_date($request->created_at)]) }}</flux:text>
                </div>

                @if ($request->isEditable() && auth()->user()->can('hrm.recruitment.request'))
                    <div class="flex gap-2">
                        <flux:button icon="pencil-square" x-on:click="$dispatch('edit-manpower-request', { id: {{ $request->id }} })">{{ __('Edit') }}</flux:button>
                        <flux:button variant="primary" icon="paper-airplane" x-on:click="$dispatch('confirm', {
                            title: {{ Js::from(__('Send for approval?')) }},
                            message: {{ Js::from(__('You cannot change the request while it is waiting for approval.')) }},
                            confirmText: {{ Js::from(__('Send')) }},
                            onConfirm: () => $wire.submit(),
                        })">{{ $status ? __('Send again') : __('Send for approval') }}</flux:button>
                    </div>
                @elseif ($request->approved_at && $request->openingsToAdvertise() > 0 && auth()->user()->can('hrm.recruitment.manage'))
                    {{-- Approved: HR can advertise it. Opens the posting form with this request already chosen. --}}
                    <flux:button variant="primary" icon="megaphone" :href="route('hrm.recruitment.postings', ['for' => $request->id])" wire:navigate>
                        {{ __('Create job posting') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="glass overflow-hidden rounded-2xl lg:col-span-2">
            <div class="p-6">
                <flux:heading class="mb-4">{{ __('Details') }}</flux:heading>
                @include('hrm::approvals.manpower-request', ['document' => $request])
            </div>
        </div>

        <div class="glass overflow-hidden rounded-2xl">
            <div class="p-6">
                <flux:heading class="mb-4">{{ __('Approval') }}</flux:heading>
                @if ($status)
                    <x-approval-timeline :document="$request" />
                @else
                    <flux:text>{{ __('Not sent yet. Check the details, then press "Send for approval".') }}</flux:text>
                @endif
            </div>
        </div>
    </div>
</div>
