<?php

namespace App\Modules\Hrm\Livewire\Employees;

use App\Modules\Hrm\Models\Employee;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Base for the "Edit" modals on the employee profile (Personal, Job ...). [EMP-01]
 * Like LookupForm (1.7), but for ONE section of ONE employee, and every action checks
 * EmployeePolicy::update (permission + org-unit scope).
 *
 * A child class declares:
 *     section()      'personal' → browser event "edit-employee-personal", modal "employee-personal-form"
 *     fieldRules()   field => rules; its keys are also the fields the form edits
 *     view()         the Blade view with the inputs (wire:model="form.first_name")
 * Optional: labels(), viewData(), check($values, $employee), saved($employee, $before).
 *
 * Placed on the profile page:   <livewire:hrm::employees.personal-form :$employee />
 * Opened by a button:           x-on:click="$dispatch('edit-employee-personal')"
 */
abstract class EmployeeSectionForm extends Component
{
    /** Locked: the browser cannot change which employee is edited. */
    #[Locked]
    public int $employeeId;

    /** @var array<string, mixed> field => value */
    public array $form = [];

    abstract protected function section(): string;

    /** @return array<string, mixed> */
    abstract protected function fieldRules(): array;

    abstract protected function view(): string;

    /** @return array<string, string> */
    protected function labels(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    protected function viewData(): array
    {
        return [];
    }

    /**
     * Rules that need the database. Add an error and return false to stop the save.
     *
     * @param  array<string, mixed>  $values
     */
    protected function check(array $values, Employee $employee): bool
    {
        return true;
    }

    /**
     * Runs after the update, inside the same transaction. $before = the values before the change.
     *
     * @param  array<string, mixed>  $before
     */
    protected function saved(Employee $employee, array $before): void {}

    public function mount(Employee $employee): void
    {
        $this->employeeId = $employee->id;
    }

    /** @return array<string, string> */
    protected function getListeners(): array
    {
        return ['edit-employee-'.$this->section() => 'open'];
    }

    public function open(): void
    {
        $employee = $this->employee();

        $this->form = [];
        foreach (array_keys($this->fieldRules()) as $field) {
            $value = $employee->getAttribute($field);
            $this->form[$field] = match (true) {
                $value instanceof Carbon => $value->toDateString(),   // <input type="date"> wants 2026-11-07
                $value instanceof \BackedEnum => $value->value,       // Gender::Male → 1 for the <select>
                $value === null => '',
                default => $value,
            };
        }
        $this->resetValidation();

        Flux::modal($this->modalName())->show();
    }

    public function save(): void
    {
        $employee = $this->employee();
        $this->validate();

        $values = array_map(fn ($v) => $v === '' ? null : $v, array_intersect_key($this->form, $this->fieldRules()));

        if (! $this->check($values, $employee)) {
            return;
        }

        // Transaction: the update, the activity log and anything saved() does are stored together, or not at all.
        DB::connection($employee->getConnectionName())->transaction(function () use ($employee, $values) {
            $before = $employee->only(array_keys($values));
            $employee->update($values);
            $this->saved($employee, $before);
        });

        Flux::modal($this->modalName())->close();
        // flash(): the toast survives the page reload below.
        session()->flash('toast', ['message' => __(':name saved.', ['name' => $employee->name]), 'variant' => 'success']);
        // Reload the profile (same tab) so the read-only cards show the new values.
        $this->redirect(route('hrm.employees.show', ['employee' => $employee, 'tab' => $this->section()]), navigate: true);
    }

    /** Loads the employee (404 if out of your scope) and checks EmployeePolicy::update (403). */
    protected function employee(): Employee
    {
        $employee = Employee::findOrFail($this->employeeId);
        Gate::authorize('update', $employee);

        return $employee;
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return collect($this->fieldRules())->mapWithKeys(fn ($rules, $field) => ["form.{$field}" => $rules])->all();
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return collect($this->labels())->mapWithKeys(fn ($label, $field) => ["form.{$field}" => $label])->all();
    }

    protected function modalName(): string
    {
        return 'employee-'.$this->section().'-form';
    }

    public function render(): View
    {
        return view($this->view(), ['modal' => $this->modalName(), ...$this->viewData()]);
    }
}
