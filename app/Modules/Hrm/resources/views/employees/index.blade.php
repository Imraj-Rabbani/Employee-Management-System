{{-- HRM → Employees. The list is a DataTable (Livewire\Employees\EmployeeTable). [EMP-05] --}}
<x-layouts.app :title="__('Employees')">
    <div class="space-y-4">
        <flux:subheading>{{ __('Everyone employed by the company. Filter by org unit, status, grade or type, choose columns and export to Excel.') }}</flux:subheading>

        <livewire:hrm::employees.employee-table />
    </div>
</x-layouts.app>
