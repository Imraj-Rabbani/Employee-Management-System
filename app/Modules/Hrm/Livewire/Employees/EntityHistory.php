<?php

namespace App\Modules\Hrm\Livewire\Employees;

use App\Modules\Hrm\Actions\ChangeEntity;
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
 * Profile → History → "Entities": the current entity of each type, the full timeline,
 * "Change entity" and "Undo" (per type). All changes go through the ChangeEntity action.
 */
class EntityHistory extends Component
{
    #[Locked]
    public int $employeeId;

    /** @var array{entity_id: string, effective_from: string, reason: string} */
    public array $form = ['entity_id' => '', 'effective_from' => '', 'reason' => ''];

    public function mount(Employee $employee): void
    {
        $this->employeeId = $employee->id;
    }

    public function open(): void
    {
        $this->employee(update: true);
        $this->form = ['entity_id' => '', 'effective_from' => today()->toDateString(), 'reason' => ''];
        $this->resetValidation();

        Flux::modal('employee-entity-change')->show();
    }

    public function save(ChangeEntity $action): void
    {
        $employee = $this->employee(update: true);

        $this->validate([
            'form.entity_id' => ['required', Rule::in(array_keys($this->entities()))],
            'form.effective_from' => ['required', 'date'],
            'form.reason' => ['nullable', 'string', 'max:255'],
        ], attributes: ['form.entity_id' => __('entity'), 'form.effective_from' => __('from')]);

        if ($problem = $action->problem($employee, (int) $this->form['entity_id'], $this->form['effective_from'])) {
            $this->addError('form.'.$problem['field'], $problem['message']);

            return;
        }

        $row = $action->handle($employee, (int) $this->form['entity_id'], $this->form['effective_from'], $this->form['reason'] ?: null);

        Flux::modal('employee-entity-change')->close();
        $this->done($employee, __(':name moved to :entity.', ['name' => $employee->name, 'entity' => $row->entity->label()]));
    }

    public function undo(int $typeId, ChangeEntity $action): void
    {
        $employee = $this->employee(update: true);

        if ($message = $action->undoProblem($employee, $typeId)) {
            $this->dispatch('toast', message: $message, variant: 'error');

            return;
        }

        // Undo puts the employee back in the previous entity: that entity must be in your scope too.
        $previous = $employee->entityAssignments()->where('entity_type_id', $typeId)->skip(1)->first();
        if ($previous && ! array_key_exists($previous->entity_id, $this->entities())) {
            $this->dispatch('toast', message: __('You cannot undo this: the previous entity is outside your access.'), variant: 'error');

            return;
        }

        $action->undoLast($employee, $typeId);
        $this->done($employee, __('Last entity change undone.'));
    }

    /** Loads the employee (404 outside your scope); with update: true also checks EmployeePolicy::update (403). */
    private function employee(bool $update = false): Employee
    {
        $employee = Employee::findOrFail($this->employeeId);
        Gate::authorize($update ? 'update' : 'view', $employee);

        return $employee;
    }

    /** @return array<int, string> entities you may move someone to: id => "Finance (Department)" */
    private function entities(): array
    {
        return app(EmployeeVisibility::class)->entityOptionsFor(Auth::user());
    }

    private function done(Employee $employee, string $message): void
    {
        session()->flash('toast', ['message' => $message, 'variant' => 'success']);
        $this->redirect(route('hrm.employees.show', ['employee' => $employee, 'tab' => 'history']), navigate: true);
    }

    public function render(): View
    {
        $employee = $this->employee();
        $rows = $employee->entityAssignments()->with(['entity', 'type'])->get();

        return view('hrm::livewire.employees.entity-history', [
            'employee' => $employee,
            'rows' => $rows,
            // The newest row of each type that has an older one can be undone: [row id => type id].
            'undoable' => $rows->groupBy('entity_type_id')->filter(fn ($group) => $group->count() > 1)
                ->mapWithKeys(fn ($group, $typeId) => [$group->first()->id => (int) $typeId])->all(),
            'canUpdate' => Gate::allows('update', $employee),
            'entities' => $this->entities(),
        ]);
    }
}
