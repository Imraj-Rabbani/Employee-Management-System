{{--
    Payroll → Attendance → Overtime (7.7). Overtime requests of the employees the user can see; ask for them.
    Approvers answer in their Approvals inbox and may change the hours. [TA-07]
--}}
<x-layouts.app :title="__('Attendance')">
    <div class="space-y-4">
        <x-ui.section-tabs section="payroll.attendance" />

        <div class="flex flex-wrap items-center justify-between gap-4">
            <flux:subheading>{{ __('Asked, recommended and approved hours. Only approved hours are paid, at the rate of the day type.') }}</flux:subheading>
            <flux:button variant="primary" icon="plus" x-on:click="$dispatch('create-overtime')">{{ __('Ask for overtime') }}</flux:button>
        </div>

        <livewire:payroll::attendance.overtime-table />
        <livewire:payroll::attendance.overtime-form />
    </div>
</x-layouts.app>
