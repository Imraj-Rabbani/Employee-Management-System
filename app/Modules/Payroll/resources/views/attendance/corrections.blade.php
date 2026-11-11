{{--
    Payroll → Attendance → Corrections (7.6). Every correction of the employees the user can see; HR can ask one
    for anyone of them. Approvers answer in their Approvals inbox. [TA-06]
--}}
<x-layouts.app :title="__('Attendance')">
    <div class="space-y-4">
        <x-ui.section-tabs section="payroll.attendance" />

        <div class="flex flex-wrap items-center justify-between gap-4">
            <flux:subheading>{{ __('Corrected in / out times. They count once approved, and the day is then processed again.') }}</flux:subheading>
            <flux:button variant="primary" icon="plus" x-on:click="$dispatch('create-correction')">{{ __('New correction') }}</flux:button>
        </div>

        @can('payroll.attendance.setup')
            <livewire:payroll::attendance.correction-settings />
        @endcan

        <livewire:payroll::attendance.correction-table />
        <livewire:payroll::attendance.correction-form />
    </div>
</x-layouts.app>
