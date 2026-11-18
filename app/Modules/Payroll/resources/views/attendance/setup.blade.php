{{--
    Payroll → Attendance setup. One view for every section; the route passes $section (routes/web.php).
    Each section = a DataTable + its modal form (App\Modules\Payroll\Livewire\Attendance). Same layout as HRM → Organization.
--}}
@php
    $sections = [
        'shifts' => ['shift-table', 'shift-form', __('Working hours, grace time and half-day rules. Employees without a shift use the default one.')],
        'weekends' => ['weekend-profile-table', 'weekend-profile-form', __('The weekly days off, e.g. Friday and Saturday. A day may alternate (every second Saturday).')],
        'holidays' => ['holiday-table', 'holiday-form', __('Public and festival holidays. A holiday counts for the employees whose holiday calendar contains it.')],
        'holiday-profiles' => ['holiday-profile-table', 'holiday-profile-form', __('Groups of holidays, e.g. "Head office" and "Factory". Each employee gets one.')],
        'devices' => ['device-table', 'device-form', __('ZKTeco attendance devices. Their punches are pulled every 5 minutes.')],
    ];
    [$table, $form, $intro] = $sections[$section];
@endphp

<x-layouts.app :title="__('Attendance')">
    <div class="space-y-4">
        <x-ui.section-tabs section="payroll.attendance" />

        <div class="flex items-center justify-between gap-4">
            <flux:subheading>{{ $intro }}</flux:subheading>
        </div>

        {{-- Devices tab: also the Self Service web punch settings. --}}
        @if ($section === 'devices')
            <livewire:payroll::attendance.punch-settings />
        @endif

        @livewire('payroll::attendance.'.$table, key($table))
        @livewire('payroll::attendance.'.$form, key($form))
    </div>
</x-layouts.app>
