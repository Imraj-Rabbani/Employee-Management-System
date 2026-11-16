{{-- My overtime (Self Service → My Attendance, 7.7). Nothing at all without a linked employee record. --}}
<div>
    @if ($employee)
        <div class="glass overflow-hidden rounded-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
                <div>
                    <flux:heading size="lg">{{ __('My overtime') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Ask for the extra hours you worked (or will work). Only approved hours are paid.') }}</flux:text>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <flux:button size="sm" variant="primary" icon="plus" x-on:click="$dispatch('create-overtime')">{{ __('Ask for overtime') }}</flux:button>
                </div>
            </div>

            @if ($requests->isEmpty())
                <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
                    <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                        <flux:icon name="clock" />
                    </div>
                    <flux:heading size="lg" class="mt-4">{{ __('No overtime requests yet') }}</flux:heading>
                </div>
            @else
                <ul class="divide-y divide-white/50 dark:divide-white/10">
                    @foreach ($requests as $request)
                        @php
                            [$label, $color] = $request->statusBadge();
                            $answer = $request->approvals->whereIn('status', [\App\Modules\Workflow\Enums\ApprovalStatus::Review, \App\Modules\Workflow\Enums\ApprovalStatus::Rejected])
                                ->sortByDesc('responded_at')->first();
                        @endphp
                        <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-6 py-3 text-sm" wire:key="overtime-{{ $request->id }}">
                            <x-ui.badge :$color>{{ $label }}</x-ui.badge>
                            <span class="font-medium">{{ format_date($request->date) }}</span>
                            <span>{{ __(':hours h asked', ['hours' => \App\Modules\Payroll\Models\OvertimeRequest::hoursLabel($request->hours)]) }}
                                @if ($request->approved_hours !== null)
                                    · {{ __(':hours h approved', ['hours' => \App\Modules\Payroll\Models\OvertimeRequest::hoursLabel($request->approved_hours)]) }}
                                @endif
                            </span>
                            <span class="text-zinc-500">{{ $request->detail }}</span>
                            @if ($request->isEditable())
                                <flux:button size="xs" icon="pencil-square" class="ms-auto" x-on:click="$dispatch('edit-overtime', { id: {{ $request->id }} })">{{ __('Change and send again') }}</flux:button>
                            @endif
                            @if ($answer?->comment && ! $request->approved_at)
                                <p class="w-full text-zinc-600 dark:text-zinc-300">{{ __('Approver:') }} {{ $answer->comment }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <livewire:payroll::attendance.overtime-form :self="true" />
    @endif
</div>
