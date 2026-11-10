<?php

namespace App\Modules\Hrm\Livewire\Employees;

use App\Modules\Hrm\Actions\ChangeOrgUnit;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Services\EmployeeVisibility;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Profile → History → "Org unit history": the timeline, "Change org unit" and "Undo". [EMP-04]
 * All changes go through the ChangeOrgUnit action.
 */
class OrgHistory extends Component
{
    #[Locked]
    public int $employeeId;

    /** @var array{org_unit_id: string, effective_from: string, reason: string} */
    public array $form = ['org_unit_id' => '', 'effective_from' => '', 'reason' => ''];

    public function mount(Employee $employee): void
    {
        $this->employeeId = $employee->id;
    }

    public function open(): void
    {
        $this->employee(update: true);
        $this->form = ['org_unit_id' => '', 'effective_from' => today()->toDateString(), 'reason' => ''];
        $this->resetValidation();

        Flux::modal('employee-org-change')->show();
    }

    public function save(ChangeOrgUnit $action): void
    {
        $employee = $this->employee(update: true);

        $this->validate([
            'form.org_unit_id' => ['required', Rule::in(array_keys($this->orgUnits()))],
            'form.effective_from' => ['required', 'date'],
            'form.reason' => ['nullable', 'string', 'max:255'],
        ], attributes: ['form.org_unit_id' => __('org unit'), 'form.effective_from' => __('from')]);

        if ($problem = $action->problem($employee, (int) $this->form['org_unit_id'], $this->form['effective_from'])) {
            $this->addError('form.'.$problem['field'], $problem['message']);

            return;
        }

        $action->handle($employee, (int) $this->form['org_unit_id'], $this->form['effective_from'], $this->form['reason'] ?: null);

        Flux::modal('employee-org-change')->close();
        $this->done($employee, __(':name moved to :unit.', ['name' => $employee->name, 'unit' => $employee->refresh()->orgUnit?->name]));
    }

    public function undo(ChangeOrgUnit $action): void
    {
        $employee = $this->employee(update: true);

        if ($message = $action->undoProblem($employee)) {
            $this->dispatch('toast', message: $message, variant: 'error');

            return;
        }

        // Undo puts the employee back in the previous unit: that unit must be in your scope too.
        $previous = $employee->orgAssignments()->skip(1)->first();
        if ($previous && ! array_key_exists($previous->org_unit_id, $this->orgUnits())) {
            $this->dispatch('toast', message: __('You cannot undo this: the previous org unit is outside your access.'), variant: 'error');

            return;
        }

        $action->undoLast($employee);
        $this->done($employee, __('Last org unit change undone.'));
    }

    /** Loads the employee (404 outside your scope); with update: true also checks EmployeePolicy::update (403). */
    private function employee(bool $update = false): Employee
    {
        $employee = Employee::findOrFail($this->employeeId);
        Gate::authorize($update ? 'update' : 'view', $employee);

        return $employee;
    }

    /** @return array<int, string> */
    private function orgUnits(): array
    {
        return app(EmployeeVisibility::class)->orgUnitOptionsFor(Auth::user());
    }

    private function done(Employee $employee, string $message): void
    {
        session()->flash('toast', ['message' => $message, 'variant' => 'success']);
        $this->redirect(route('hrm.employees.show', ['employee' => $employee, 'tab' => 'history']), navigate: true);
    }

    public function render(): View
    {
        $employee = $this->employee();

        return view('hrm::livewire.employees.org-history', [
            'employee' => $employee,
            'rows' => $employee->orgAssignments()->with('orgUnit')->get(),
            'canUpdate' => Gate::allows('update', $employee),
            'units' => $this->orgUnits(),
        ]);
    }
}
