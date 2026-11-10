{{--
    Payroll → Attendance → Punches (7.3c). Every punch from devices, files and web punch; upload a file. [TA-04]
--}}
<x-layouts.app :title="__('Attendance')">
    <div class="space-y-4">
        <x-ui.section-tabs section="payroll.attendance" />

        <div class="flex flex-wrap items-center justify-between gap-4">
            <flux:subheading>{{ __('Raw punches from devices, uploads and web punch. They are never changed: wrong attendance is fixed with a correction.') }}</flux:subheading>
            <livewire:payroll::attendance.punch-upload />
        </div>

        <livewire:payroll::attendance.punch-table />
    </div>
</x-layouts.app>
