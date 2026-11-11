{{-- Self Service → My Team Attendance (7.8): the people below me in the supervisor chain. [TA-08, ESS-04] --}}
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
