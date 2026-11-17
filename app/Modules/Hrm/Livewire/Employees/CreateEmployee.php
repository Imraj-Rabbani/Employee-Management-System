<?php

namespace App\Modules\Hrm\Livewire\Employees;

use App\Modules\Hrm\Actions\ChangeEntity;
use App\Modules\Hrm\Enums\Gender;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmploymentType;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Hrm\Services\EmployeeVisibility;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * HRM → Employees → "New employee" (modal "employee-create-form").
 *
 * Only the basics; the rest is filled in on the profile, where the user lands after saving.
 * The entity must be one the user may see, or they would create someone they cannot open.
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
            'join_date' => now()->toDateString(), 'designation_id' => '',
            'employment_type_id' => '', 'grade_id' => '',
            // One dropdown per entity type: [typeId => entityId]. The user's own entity is preselected
            // when they are limited to one (that type is then required).
            'entities' => collect($this->choices())->map(fn ($c) => $c['required'] && count($c['options']) === 1 ? (string) array_key_first($c['options']) : '')->all(),
        ];
        $this->resetValidation();

        Flux::modal('employee-create-form')->show();
    }

    public function save(): void
    {
        Gate::authorize('create', Employee::class);

        $choices = $this->choices();
        $values = $this->validate([
            ...EmployeeVisibility::entityChoiceRules($choices, 'form.entities'),
            'form.code' => ['required', 'string', 'max:50', Rule::unique(Employee::class, 'code')],
            'form.title' => ['nullable', 'string', 'max:20'],
            'form.first_name' => ['required', 'string', 'max:100'],
            'form.last_name' => ['nullable', 'string', 'max:100'],
            'form.name_local' => ['nullable', 'string', 'max:200'],
            'form.gender' => ['nullable', Rule::enum(Gender::class)],
            'form.join_date' => ['required', 'date'],
            'form.designation_id' => ['nullable', Rule::exists(Designation::class, 'id')],
            'form.employment_type_id' => ['nullable', Rule::exists(EmploymentType::class, 'id')],
            'form.grade_id' => ['nullable', Rule::exists(Grade::class, 'id')],
        ], attributes: [
            'form.code' => __('employee code'), 'form.first_name' => __('first name'), 'form.join_date' => __('join date'),
            ...collect($choices)->mapWithKeys(fn ($c, $typeId) => ["form.entities.{$typeId}" => mb_strtolower($c['name'])])->all(),
        ])['form'];

        $entityIds = $values['entities'] ?? [];
        unset($values['entities']);

        // One transaction: an employee is never saved without the entities that make them visible.
        $employee = DB::connection((new Employee)->getConnectionName())->transaction(function () use ($values, $entityIds) {
            $employee = Employee::create(array_map(fn ($v) => $v === '' ? null : $v, $values));
            app(ChangeEntity::class)->assignOnJoining($employee, $entityIds);

            return $employee;
        });

        session()->flash('toast', ['message' => __(':name added.', ['name' => $employee->name]), 'variant' => 'success']);
        $this->redirect(route('hrm.employees.show', $employee), navigate: true);
    }

    /** @return array<int, array{name: string, options: array<int, string>, required: bool}> one dropdown per entity type */
    private function choices(): array
    {
        return app(EmployeeVisibility::class)->entityChoicesFor(Auth::user());
    }

    public function render(): View
    {
        return view('hrm::livewire.employees.create-employee', [
            'genders' => Gender::options(),
            'choices' => $this->choices(),
            'designations' => Designation::orderBy('name')->pluck('name', 'id')->all(),
            'types' => EmploymentType::orderBy('name')->pluck('name', 'id')->all(),
            'grades' => Grade::ordered()->pluck('name', 'id')->all(),
        ]);
    }
}
