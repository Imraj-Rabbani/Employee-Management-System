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

                <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                            __('Employee') => ($application->employee?->code ?? '').' · '.($application->employee?->name ?? '?'),
                            __('Leave type') => $application->leaveType?->optionLabel(),
                            __('Dates') => $application->datesLabel(),
                            __('Days counted') => $num($application->days).' · '.$application->leaveDays->map(fn ($d) => $d->date->translatedFormat('D j M').($d->days < 1 ? ' (½)' : ''))->implode(', '),
                            __('Delegatee') => $application->delegatee?->name,
                            __('Contact during leave') => $application->contact_during_leave,
                            __('Reason') => $application->reason,
                            __('Attachment') => $application->attachment_name,
                            __('Applied on') => format_datetime($application->created_at),
                    ] as $detailLabel => $detailValue)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $detailLabel }}</dt>
                            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                @if ($detailValue === null || $detailValue === '')
                                    <span class="text-zinc-400">—</span>
                                @else
                                    {{ $detailValue }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
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
                                <flux:field>
                                    <flux:label>{{ __('Withdraw') }}</flux:label>
                                    <flux:description>{{ $application->approved_at ? __('The days go back to the balance.') : __('The approvers are no longer asked.') }}</flux:description>
                                    <flux:input wire:model="withdrawReason" :placeholder="__('Reason (optional)')" />
                                    <flux:error name="withdrawReason" />
                                </flux:field>
                            </div>
                            <flux:button variant="danger" icon="x-mark" x-on:click="$dispatch('confirm', {
                                title: {{ Js::from(__('Withdraw this leave?')) }},
                                message: {{ Js::from($application->approved_at ? __('The days go back to the balance and attendance is processed again.') : __('The approvers are no longer asked.')) }},
                                confirmText: {{ Js::from(__('Withdraw')) }},
                                onConfirm: () => $wire.withdraw(),
                            })">{{ __('Withdraw') }}</flux:button>
                        </div>
                    @endif
                @endif
            </div>
        @endif
    </x-ui.modal>
</div>
