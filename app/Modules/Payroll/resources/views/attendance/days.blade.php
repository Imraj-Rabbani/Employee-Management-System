{{--
    Payroll → Attendance → Daily attendance (7.5). The processed days, and "Process again" for a date range. [TA-05, TA-06]
--}}
<x-layouts.app :title="__('Attendance')">
    <div class="space-y-4">
        <x-ui.section-tabs section="payroll.attendance" />

        <div class="flex flex-wrap items-center justify-between gap-4">
            <flux:subheading>{{ __('Each employee\'s day, worked out every night from punches, rosters and holidays. Approved rosters, holiday changes and new punches update the days by themselves.') }}</flux:subheading>
            <livewire:payroll::attendance.attendance-reprocess />
        </div>

        <livewire:payroll::attendance.attendance-day-table />
    </div>
</x-layouts.app>
