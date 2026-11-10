{{-- HRM → Employees. The list is a DataTable (Livewire\Employees\EmployeeTable). [EMP-05] --}}
<x-layouts.app :title="__('Employees')">
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-4">
            <flux:subheading>{{ __('Everyone employed by the company. Filter by org unit, status, grade or type, choose columns and export to Excel.') }}</flux:subheading>

            @can('create', App\Modules\Hrm\Models\Employee::class)
                <div class="flex shrink-0 gap-2">
                    <flux:button icon="arrow-up-tray" :href="route('hrm.employees.import')" wire:navigate>{{ __('Import') }}</flux:button>
                    @if (App\Modules\Hrm\Livewire\Employees\InviteUser::allowed())
                        <flux:button icon="envelope" x-on:click="$dispatch('invite-user')">{{ __('Invite user') }}</flux:button>
                    @endif
                    <flux:button variant="primary" icon="plus" x-on:click="$dispatch('create-employee')">{{ __('New employee') }}</flux:button>
                </div>
            @endcan
        </div>

        {{-- Invite by email: the person registers themselves and becomes an employee. Also shows pending invitations. --}}
        @if (App\Modules\Hrm\Livewire\Employees\InviteUser::allowed())
            <livewire:hrm::employees.invite-user />
        @endif

        <livewire:hrm::employees.employee-table />

        @can('create', App\Modules\Hrm\Models\Employee::class)
            <livewire:hrm::employees.create-employee />
        @endcan
    </div>
</x-layouts.app>
