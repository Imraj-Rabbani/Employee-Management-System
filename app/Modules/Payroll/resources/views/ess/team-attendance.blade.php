{{-- Self Service → My Team Attendance: the people below me in the supervisor chain. --}}
<x-layouts.app :title="__('My Team Attendance')">
    <div class="space-y-4">
        @if ($employeeId)
            <flux:link :href="route('ess.team-attendance', ['month' => request('month')])" wire:navigate icon="arrow-left" class="text-sm">{{ __('Back to my team') }}</flux:link>
            <livewire:payroll::attendance.employee-calendar mode="team" :employee-id="$employeeId" />
        @else
            <livewire:payroll::attendance.team-calendar mode="team" />
        @endif
    </div>
</x-layouts.app>
