{{-- Self Service → My Attendance: web punch (7.3e) and my corrections (7.6). My calendar comes in 7.8. --}}
<x-layouts.app :title="__('My Attendance')">
    <div class="space-y-6">
        <livewire:payroll::ess.web-punch />
        <livewire:payroll::ess.my-corrections />
    </div>
</x-layouts.app>
