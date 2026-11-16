{{-- Self Service → My Attendance: web punch, my calendar, my corrections, my overtime. --}}
<x-layouts.app :title="__('My Attendance')">
    <div class="space-y-6">
        <livewire:payroll::ess.web-punch />
        <livewire:payroll::attendance.employee-calendar mode="self" />
        <livewire:payroll::ess.my-corrections />
        <livewire:payroll::ess.my-overtime />
    </div>
</x-layouts.app>
