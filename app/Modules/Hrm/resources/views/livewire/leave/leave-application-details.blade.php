{{-- One leave application (LeaveApplicationDetails): details, days, timeline, change / withdraw. --}}
<div>
    <x-ui.modal name="leave-details" size="lg" :heading="$application ? __('Leave :no', ['no' => $application->number()]) : __('Leave')">
        @if ($application)
            @php
                [$label, $color] = $application->statusBadge();
                $num = fn ($v) => \App\Modules\Hrm\Models\LeaveApplication::daysLabel($v);
            @endphp
            <div class="space-y-6">
                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.badge :$color>{{ $label }}</x-ui.badge>
                    @if ($application->withdrawn_at)
                        <flux:text class="text-sm">{{ __('on :date', ['date' => format_datetime($application->withdrawn_at)]) }}
                            @if ($application->withdraw_reason) · {{ $application->withdraw_reason }} @endif
                        </flux:text>
                    @endif
                </div>

                <x-ui.details :items="[
                    __('Employee') => ($application->employee?->code ?? '').' · '.($application->employee?->name ?? '?'),
                    __('Leave type') => $application->leaveType?->optionLabel(),
                    __('Dates') => $application->datesLabel(),
                    __('Days counted') => $num($application->days).' · '.$application->leaveDays->map(fn ($d) => $d->date->translatedFormat('D j M').($d->days < 1 ? ' (½)' : ''))->implode(', '),
                    __('Delegatee') => $application->delegatee?->name,
                    __('Contact during leave') => $application->contact_during_leave,
                    __('Reason') => $application->reason,
                    __('Attachment') => $application->attachment_name,
                    __('Applied on') => format_datetime($application->created_at),
                ]" />
                @if ($application->attachment_path)
                    <flux:button size="sm" icon="paper-clip" :href="route('hrm.leave.attachment', $application->id)">{{ __('Download attachment') }}</flux:button>
                @endif

                <div>
                    <flux:heading size="sm" class="mb-3">{{ __('Approval') }}</flux:heading>
                    <x-approval-timeline :document="$application" />
                </div>

                @if ($application->isEditable())
                    <flux:button icon="pencil-square" x-on:click="$flux.modal('leave-details').close(); $dispatch('edit-leave', { id: {{ $application->id }} })">
                        {{ __('Change and send again') }}
                    </flux:button>
                @endif

                @if ($application->isActive())
                    <flux:separator />
                    @if ($withdrawProblem)
                        <flux:text class="text-sm">{{ $withdrawProblem }}</flux:text>
                    @else
                        <div class="flex flex-wrap items-end gap-3">{{-- not a form: Enter must not withdraw without the confirmation --}}
                            <div class="min-w-64 flex-1">
                                <x-form.input wire:model="withdrawReason" :label="__('Withdraw')" :placeholder="__('Reason (optional)')"
                                    :hint="$application->approved_at ? __('The days go back to the balance.') : __('The approvers are no longer asked.')" />
                            </div>
                            <flux:button variant="danger" icon="x-mark" x-on:click="$dispatch('confirm', {
                                title: @js(__('Withdraw this leave?')),
                                message: @js($application->approved_at ? __('The days go back to the balance and attendance is processed again.') : __('The approvers are no longer asked.')),
                                confirmText: @js(__('Withdraw')),
                                onConfirm: () => $wire.withdraw(),
                            })">{{ __('Withdraw') }}</flux:button>
                        </div>
                    @endif
                @endif
            </div>
        @endif
    </x-ui.modal>
</div>
