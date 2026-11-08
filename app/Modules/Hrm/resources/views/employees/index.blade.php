{{-- HRM → Employees. The list is a DataTable (Livewire\Employees\EmployeeTable). [EMP-05] --}}
<x-layouts.app :title="__('Employees')">
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-4">
            <flux:subheading>{{ __('Everyone employed by the company. Filter by org unit, status, grade or type, choose columns and export to Excel.') }}</flux:subheading>

            @can('create', App\Modules\Hrm\Models\Employee::class)
                <flux:button variant="primary" icon="plus" x-on:click="$dispatch('create-employee')">{{ __('New employee') }}</flux:button>
            @endcan
        </div>

        <livewire:hrm::employees.employee-table />

        @can('create', App\Modules\Hrm\Models\Employee::class)
            <livewire:hrm::employees.create-employee />
        @endcan
    </div>
</x-layouts.app>
