{{-- My corrections (Self Service → My Attendance, 7.6). Nothing at all without a linked employee record. --}}
<div>
    @if ($employee)
        <div class="glass overflow-hidden rounded-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
                <div>
                    <flux:heading size="lg">{{ __('My corrections') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Forgot to punch, or a wrong time? Ask for a correction: it counts once approved.') }}</flux:text>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <flux:button size="sm" variant="primary" icon="plus" x-on:click="$dispatch('create-correction')">{{ __('Request a correction') }}</flux:button>
                </div>
            </div>

            @if ($corrections->isEmpty())
                <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
                    <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                        <flux:icon name="clock" />
                    </div>
                    <flux:heading size="lg" class="mt-4">{{ __('No corrections yet') }}</flux:heading>
                </div>
            @else
                <ul class="divide-y divide-white/50 dark:divide-white/10">
                    @foreach ($corrections as $correction)
                        @php
                            [$label, $color] = $correction->statusBadge();
                            $answer = $correction->approvals->whereIn('status', [\App\Modules\Workflow\Enums\ApprovalStatus::Review, \App\Modules\Workflow\Enums\ApprovalStatus::Rejected])
                                ->sortByDesc('responded_at')->first();
                        @endphp
                        <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-6 py-3 text-sm" wire:key="correction-{{ $correction->id }}">
                            <x-ui.badge :$color>{{ $label }}</x-ui.badge>
                            <span class="font-medium">{{ format_date($correction->date) }}</span>
                            <span>{{ __('in') }} {{ \App\Modules\Payroll\Models\AttendanceCorrection::timeLabel($correction->requested_in_at, $correction->date) }},
                                {{ __('out') }} {{ \App\Modules\Payroll\Models\AttendanceCorrection::timeLabel($correction->requested_out_at, $correction->date) }}</span>
                            <span class="text-zinc-500">{{ $correction->reason }}</span>
                            @if ($correction->isEditable())
                                <flux:button size="xs" icon="pencil-square" class="ms-auto" x-on:click="$dispatch('edit-correction', { id: {{ $correction->id }} })">{{ __('Change and send again') }}</flux:button>
                            @endif
                            @if ($answer?->comment && ! $correction->approved_at)
                                <p class="w-full text-zinc-600 dark:text-zinc-300">{{ __('Approver:') }} {{ $answer->comment }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <livewire:payroll::attendance.correction-form :self="true" />
    @endif
</div>
