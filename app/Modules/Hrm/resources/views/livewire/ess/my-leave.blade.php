{{-- My Leave Requests (Self Service, 8.4). Nothing at all without a linked employee record. --}}
@php $num = fn ($v) => \App\Modules\Hrm\Models\LeaveApplication::daysLabel($v); @endphp
<div class="space-y-4">
    @if ($employee)
        <div class="glass overflow-hidden rounded-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
                <div>
                    <flux:heading size="lg">{{ __('My leave balance') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Leave year :year', ['year' => $year->label()]) }}</flux:text>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <flux:button size="sm" variant="primary" icon="plus" x-on:click="$dispatch('create-leave')">{{ __('Apply for leave') }}</flux:button>
                </div>
            </div>

            <div class="p-6">
                @if ($types->isEmpty())
                    <flux:text>{{ __('You have no leave profile yet: please ask HR.') }}</flux:text>
                @else
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($types as $type)
                            @php $balance = $balances->get($type->id); @endphp
                            <div class="rounded-lg bg-white/40 px-4 py-3 ring-1 ring-inset ring-white/60 dark:bg-white/5 dark:ring-white/10" wire:key="my-balance-{{ $type->id }}">
                                <div class="text-sm text-zinc-500">{{ $type->name }}</div>
                                <div class="text-2xl font-semibold tabular-nums">{{ $type->requires_balance ? $num($balance->balance ?? 0) : '—' }}</div>
                                <div class="text-xs text-zinc-500">{{ __(':n taken', ['n' => $num($balance->taken ?? 0)]) }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="glass overflow-hidden rounded-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
                <div>
                    <flux:heading size="lg">{{ __('My leave requests') }}</flux:heading>
                </div>
            </div>

            @if ($applications->isEmpty())
                <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
                    <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                        <flux:icon name="calendar-days" />
                    </div>
                    <flux:heading size="lg" class="mt-4">{{ __('No leave requests yet') }}</flux:heading>
                </div>
            @else
                <ul class="divide-y divide-white/50 dark:divide-white/10">
                    @foreach ($applications as $application)
                        @php [$label, $color] = $application->statusBadge(); @endphp
                        <li wire:key="my-leave-{{ $application->id }}">
                            <button type="button" class="flex w-full flex-wrap items-center gap-x-4 gap-y-1 px-6 py-3 text-left text-sm hover:bg-white/40 dark:hover:bg-white/5"
                                x-on:click="$dispatch('show-leave', { id: {{ $application->id }} })">
                                <x-ui.badge :$color>{{ $label }}</x-ui.badge>
                                <span class="font-medium">{{ $application->leaveType?->name }}</span>
                                <span>{{ $application->datesLabel() }}</span>
                                <span class="text-zinc-500">{{ __(':days days', ['days' => $num($application->days)]) }}</span>
                                <span class="ms-auto text-xs text-zinc-500">{{ $application->number() }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if ($canEncash || $encashments->isNotEmpty())
            <div class="glass overflow-hidden rounded-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
                    <div>
                        <flux:heading size="lg">{{ __('My leave encashments') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Unused days paid out with the next payroll once approved.') }}</flux:text>
                    </div>
                    @if ($canEncash)
                        <div class="flex shrink-0 items-center gap-2">
                            <flux:button size="sm" icon="banknotes" x-on:click="$dispatch('create-encashment')">{{ __('Encash leave') }}</flux:button>
                        </div>
                    @endif
                </div>

                @if ($encashments->isEmpty())
                    <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
                        <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                            <flux:icon name="banknotes" />
                        </div>
                        <flux:heading size="lg" class="mt-4">{{ __('No encashments yet') }}</flux:heading>
                    </div>
                @else
                    <ul class="divide-y divide-white/50 dark:divide-white/10">
                        @foreach ($encashments as $encashment)
                            @php [$label, $color] = $encashment->statusBadge(); @endphp
                            <li class="flex flex-wrap items-center gap-x-4 gap-y-1 px-6 py-3 text-sm" wire:key="my-encashment-{{ $encashment->id }}">
                                <x-ui.badge :$color>{{ $label }}</x-ui.badge>
                                <span class="font-medium">{{ $encashment->leaveType?->name }}</span>
                                <span>{{ __(':days days', ['days' => $num($encashment->days)]) }}</span>
                                <span class="text-xs text-zinc-500">{{ $encashment->number() }}</span>
                                <span class="ms-auto flex gap-2">
                                    @if ($encashment->isEditable())
                                        <flux:button size="xs" icon="pencil-square" x-on:click="$dispatch('edit-encashment', { id: {{ $encashment->id }} })">{{ __('Change and send again') }}</flux:button>
                                    @endif
                                    @if ($encashment->isActive() && $encashment->approved_at === null)
                                        <flux:button size="xs" variant="ghost" class="!text-red-600" x-on:click="$dispatch('confirm', {
                                            title: @js(__('Withdraw :no?', ['no' => $encashment->number()])),
                                            confirmText: @js(__('Withdraw')),
                                            onConfirm: () => $dispatch('withdraw-encashment', { id: {{ $encashment->id }} }),
                                        })">{{ __('Withdraw') }}</flux:button>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        <livewire:hrm::leave.leave-application-form :self="true" />
        <livewire:hrm::leave.encashment-form :self="true" />
        <livewire:hrm::leave.leave-application-details :self="true" />
    @else
        <div class="glass overflow-hidden rounded-2xl">
            <div class="p-6">
                <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
                    <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                        <flux:icon name="user" />
                    </div>
                    <flux:heading size="lg" class="mt-4">{{ __('Your login is not linked to an employee record') }}</flux:heading>
                    <flux:text class="mt-1 max-w-sm">{{ __('Ask HR to link it (Employees → profile → Login).') }}</flux:text>
                </div>
            </div>
        </div>
    @endif
</div>
