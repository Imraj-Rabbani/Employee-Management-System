{{--
    Payroll → Attendance → Rosters. The list + the New roster modal.
--}}
<x-layouts.app :title="__('Attendance')">
    <div class="space-y-4">
        <x-ui.section-tabs section="payroll.attendance" />

        <div class="flex flex-wrap items-center justify-between gap-4">
            <flux:subheading>{{ __('Plan who works which shift on which day. A roster counts for attendance once it is approved.') }}</flux:subheading>
            <flux:button variant="primary" icon="plus" x-on:click="$dispatch('create-roster')">{{ __('New roster') }}</flux:button>
        </div>

        <livewire:payroll::attendance.roster-table />
        <livewire:payroll::attendance.roster-form />
    </div>
</x-layouts.app>
