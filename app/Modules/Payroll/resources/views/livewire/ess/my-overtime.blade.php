{{-- My overtime (Self Service → My Attendance, 7.7). Nothing at all without a linked employee record. --}}
<div>
    @if ($employee)
        <x-ui.card :heading="__('My overtime')" :subheading="__('Ask for the extra hours you worked (or will work). Only approved hours are paid.')" :padding="false">
            <x-slot:actions>
                <flux:button size="sm" variant="primary" icon="plus" x-on:click="$dispatch('create-overtime')">{{ __('Ask for overtime') }}</flux:button>
            </x-slot:actions>

            @if ($requests->isEmpty())
                <x-ui.empty icon="clock" :heading="__('No overtime requests yet')" />
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
        </x-ui.card>

        <livewire:payroll::attendance.overtime-form :self="true" />
    @endif
</div>
