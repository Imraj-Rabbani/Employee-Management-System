{{-- Self Service → My Attendance: web punch (7.3e), my calendar (7.8), my corrections (7.6), my overtime (7.7). --}}
<x-layouts.app :title="__('My Attendance')">
    <div class="space-y-6">
        <livewire:payroll::ess.web-punch />
        <livewire:payroll::attendance.employee-calendar mode="self" />
        <livewire:payroll::ess.my-corrections />
        <livewire:payroll::ess.my-overtime />
    </div>
</x-layouts.app>
