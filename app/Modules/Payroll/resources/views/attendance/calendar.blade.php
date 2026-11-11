{{--
    Payroll → Attendance → Calendar (7.8): the team grid, or (?employee=5) one employee's month. [TA-08]
--}}
<x-layouts.app :title="__('Attendance')">
    <div class="space-y-4">
        <x-ui.section-tabs section="payroll.attendance" />

        @if ($employeeId)
            <flux:link :href="route('payroll.attendance.calendar', ['month' => request('month')])" wire:navigate icon="arrow-left" class="text-sm">{{ __('Back to the team') }}</flux:link>
            <livewire:payroll::attendance.employee-calendar mode="hr" :employee-id="$employeeId" />
        @else
            <livewire:payroll::attendance.team-calendar mode="hr" />
        @endif
    </div>
</x-layouts.app>
