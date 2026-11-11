{{-- My corrections (Self Service → My Attendance, 7.6). Nothing at all without a linked employee record. --}}
<div>
    @if ($employee)
        <x-ui.card :heading="__('My corrections')" :subheading="__('Forgot to punch, or a wrong time? Ask for a correction: it counts once approved.')" :padding="false">
            <x-slot:actions>
                <flux:button size="sm" variant="primary" icon="plus" x-on:click="$dispatch('create-correction')">{{ __('Request a correction') }}</flux:button>
            </x-slot:actions>

            @if ($corrections->isEmpty())
                <x-ui.empty icon="clock" :heading="__('No corrections yet')" />
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
        </x-ui.card>

        <livewire:payroll::attendance.correction-form :self="true" />
    @endif
</div>
