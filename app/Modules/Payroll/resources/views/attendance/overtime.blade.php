{{--
    Payroll → Attendance → Overtime. Overtime requests of the employees the user can see; ask for them.
    Approvers answer in their Approvals inbox and may change the hours.
--}}
<x-layouts.app :title="__('Attendance')">
    <div class="space-y-4">
        <x-ui.section-tabs section="payroll.attendance" />

        <div class="flex flex-wrap items-center justify-between gap-4">
            <flux:subheading>{{ __('Asked, recommended and approved hours. Only approved hours are paid, at the rate of the day type.') }}</flux:subheading>
        </div>

        <livewire:payroll::attendance.overtime-table />
        <livewire:payroll::attendance.overtime-form />
    </div>
</x-layouts.app>
