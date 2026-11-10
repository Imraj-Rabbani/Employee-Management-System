<?php

namespace App\Modules\Hrm\Livewire\Employees;

use App\Modules\Hrm\Enums\Gender;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmploymentType;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Hrm\Services\EmployeeVisibility;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * HRM → Employees → "New employee" (modal "employee-create-form"). [EMP-01]
 *
 * Only the basics; the rest is filled in on the profile, where the user lands after saving.
 * The org unit must be one the user may see, or they would create someone they cannot open.
 */
class CreateEmployee extends Component
{
    /** @var array<string, mixed> */
    public array $form = [];

    #[On('create-employee')]
    public function open(): void
    {
        Gate::authorize('create', Employee::class);

        $this->form = [
            'code' => '', 'title' => '', 'first_name' => '', 'last_name' => '', 'name_local' => '', 'gender' => '',
            'join_date' => now()->toDateString(), 'org_unit_id' => '', 'designation_id' => '',
            'employment_type_id' => '', 'grade_id' => '',
        ];
        $this->resetValidation();

        Flux::modal('employee-create-form')->show();
    }

    public function save(): void
    {
        Gate::authorize('create', Employee::class);

        $values = $this->validate([
            'form.code' => ['required', 'string', 'max:50', Rule::unique(Employee::class, 'code')],
            'form.title' => ['nullable', 'string', 'max:20'],
            'form.first_name' => ['required', 'string', 'max:100'],
            'form.last_name' => ['nullable', 'string', 'max:100'],
            'form.name_local' => ['nullable', 'string', 'max:200'],
            'form.gender' => ['nullable', Rule::enum(Gender::class)],
            'form.join_date' => ['required', 'date'],
            'form.org_unit_id' => ['required', Rule::in(array_keys($this->orgUnits()))],
            'form.designation_id' => ['nullable', Rule::exists(Designation::class, 'id')],
            'form.employment_type_id' => ['nullable', Rule::exists(EmploymentType::class, 'id')],
            'form.grade_id' => ['nullable', Rule::exists(Grade::class, 'id')],
        ], attributes: [
            'form.code' => __('employee code'), 'form.first_name' => __('first name'), 'form.join_date' => __('join date'),
            'form.org_unit_id' => __('org unit'),
        ])['form'];

        $employee = Employee::create(array_map(fn ($v) => $v === '' ? null : $v, $values));

        session()->flash('toast', ['message' => __(':name added.', ['name' => $employee->name]), 'variant' => 'success']);
        $this->redirect(route('hrm.employees.show', $employee), navigate: true);
    }

    /** @return array<int, string> */
    private function orgUnits(): array
    {
        return app(EmployeeVisibility::class)->orgUnitOptionsFor(Auth::user());
    }

    public function render(): View
    {
        return view('hrm::livewire.employees.create-employee', [
            'genders' => Gender::options(),
            'units' => $this->orgUnits(),
            'designations' => Designation::orderBy('name')->pluck('name', 'id')->all(),
            'types' => EmploymentType::orderBy('name')->pluck('name', 'id')->all(),
            'grades' => Grade::ordered()->pluck('name', 'id')->all(),
        ]);
    }
}
