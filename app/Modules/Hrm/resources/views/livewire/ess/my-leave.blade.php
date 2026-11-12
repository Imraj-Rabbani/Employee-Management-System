{{-- My Leave Requests (Self Service, 8.4). Nothing at all without a linked employee record. --}}
@php $num = fn ($v) => \App\Modules\Hrm\Models\LeaveApplication::daysLabel($v); @endphp
<div class="space-y-4">
    @if ($employee)
        <x-ui.card :heading="__('My leave balance')" :subheading="__('Leave year :year', ['year' => $year->label()])">
            <x-slot:actions>
                <flux:button size="sm" variant="primary" icon="plus" x-on:click="$dispatch('create-leave')">{{ __('Apply for leave') }}</flux:button>
            </x-slot:actions>

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
        </x-ui.card>

        <x-ui.card :heading="__('My leave requests')" :padding="false">
            @if ($applications->isEmpty())
                <x-ui.empty icon="calendar-days" :heading="__('No leave requests yet')" />
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
        </x-ui.card>

        <livewire:hrm::leave.leave-application-form :self="true" />
        <livewire:hrm::leave.leave-application-details :self="true" />
    @else
        <x-ui.card>
            <x-ui.empty icon="user" :heading="__('Your login is not linked to an employee record')"
                :description="__('Ask HR to link it (Employees → profile → Login).')" />
        </x-ui.card>
    @endif
</div>
