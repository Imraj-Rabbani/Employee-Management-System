<?php

namespace App\Modules\Hrm\Livewire\Employees;

use App\Modules\Hrm\Enums\PaymentMethod;
use App\Modules\Hrm\Models\Branch;
use App\Modules\Hrm\Models\CostCenter;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmploymentType;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Hrm\Models\Position;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Hrm\Services\EmployeeVisibility;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Profile → Job → Edit: code, placement, reporting lines, dates, payment method. [EMP-01]
 *
 * Not here on purpose: status and separation (Lifecycle, Phase 13).
 * The org unit is edited directly for now; 5.4 turns a change into a dated assignment (history).
 *
 * Extra rules:
 *   - org unit: only units the user may see
 *   - supervisor / dotted-line manager: not the employee themself, and no loop (A → B → A)
 *   - position: not held by another employee; the old seat becomes vacant, the new one filled
 */
class JobForm extends EmployeeSectionForm
{
    protected function section(): string
    {
        return 'job';
    }

    protected function fieldRules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique(Employee::class, 'code')->ignore($this->employeeId)],
            'access_code' => ['nullable', 'string', 'max:50'],
            'org_unit_id' => ['required', Rule::in(array_keys($this->orgUnits()))],
            'designation_id' => ['nullable', Rule::exists(Designation::class, 'id')],
            'grade_id' => ['nullable', Rule::exists(Grade::class, 'id')],
            'position_id' => ['nullable', Rule::exists(Position::class, 'id')],
            'branch_id' => ['nullable', Rule::exists(Branch::class, 'id')],
            'cost_center_id' => ['nullable', Rule::exists(CostCenter::class, 'id')],
            'employment_type_id' => ['nullable', Rule::exists(EmploymentType::class, 'id')],
            'supervisor_id' => ['nullable', 'integer', 'different:form.dotted_line_manager_id', Rule::exists(Employee::class, 'id')],
            'dotted_line_manager_id' => ['nullable', 'integer', Rule::exists(Employee::class, 'id')],
            'join_date' => ['required', 'date'],
            'planned_confirmation_date' => ['nullable', 'date', 'after_or_equal:form.join_date'],
            'confirmation_date' => ['nullable', 'date', 'after_or_equal:form.join_date'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
        ];
    }

    protected function labels(): array
    {
        return [
            'code' => __('employee code'), 'access_code' => __('attendance ID'), 'org_unit_id' => __('org unit'),
            'position_id' => __('position'), 'cost_center_id' => __('cost center'), 'employment_type_id' => __('employment type'),
            'supervisor_id' => __('supervisor'), 'dotted_line_manager_id' => __('dotted-line manager'),
            'join_date' => __('join date'), 'planned_confirmation_date' => __('planned confirmation'),
            'confirmation_date' => __('confirmation date'), 'payment_method' => __('payment method'),
        ];
    }

    protected function check(array $values, Employee $employee): bool
    {
        foreach (['supervisor_id', 'dotted_line_manager_id'] as $field) {
            if ($values[$field] !== null && (int) $values[$field] === $employee->id) {
                $this->addError("form.{$field}", __('An employee cannot report to themselves.'));
            }
        }
        if ($values['supervisor_id'] !== null && $employee->wouldLoopUnder((int) $values['supervisor_id'])) {
            $this->addError('form.supervisor_id', __('This would make a loop: the supervisor (indirectly) reports to this employee.'));
        }

        if ($values['position_id'] !== null) {
            $holder = Position::find($values['position_id'])?->employee_id;
            if ($holder !== null && (int) $holder !== $employee->id) {
                $this->addError('form.position_id', __('This position is already held by another employee.'));
            }
        }

        return $this->getErrorBag()->isEmpty();
    }

    /** Keeps positions in step: the seat this employee left is vacant again, the new one is filled. */
    protected function saved(Employee $employee, array $before): void
    {
        // (int): form values arrive as text ("5"), database values as numbers (5).
        $old = isset($before['position_id']) ? (int) $before['position_id'] : null;
        $new = $employee->position_id !== null ? (int) $employee->position_id : null;

        if ($old === $new) {
            return;
        }
        if ($old !== null) {
            Position::whereKey($old)->where('employee_id', $employee->id)->update(['employee_id' => null, 'is_vacant' => true]);
        }
        if ($new !== null) {
            Position::whereKey($new)->update(['employee_id' => $employee->id, 'is_vacant' => false]);
        }
    }

    protected function viewData(): array
    {
        $units = $this->orgUnits();

        return [
            'units' => $units,
            'designations' => Designation::orderBy('name')->pluck('name', 'id')->all(),
            'grades' => Grade::ordered()->pluck('name', 'id')->all(),
            'positions' => Position::where('is_active', true)->orderBy('name')->get()
                ->mapWithKeys(fn (Position $p) => [$p->id => $p->name.($p->employee_id && $p->employee_id !== $this->employeeId ? ' · '.__('filled') : '')])
                ->all(),
            'branches' => Branch::orderBy('name')->pluck('name', 'id')->all(),
            'costCenters' => CostCenter::orderBy('code')->get()->mapWithKeys(fn (CostCenter $c) => [$c->id => "{$c->code} · {$c->name}"])->all(),
            'types' => EmploymentType::orderBy('name')->pluck('name', 'id')->all(),
            'people' => $this->peopleOptions(),
            'paymentMethods' => PaymentMethod::options(),
        ];
    }

    /**
     * Possible supervisors: active employees you can see, except this one. The current supervisor is
     * added even if they are outside your scope, so the dropdown does not silently clear them.
     *
     * @return array<int, string>
     */
    private function peopleOptions(): array
    {
        $people = Employee::active()->whereKeyNot($this->employeeId)->orderBy('first_name')->get(['id', 'code', 'first_name', 'last_name'])
            ->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->name} ({$e->code})"])->all();

        $current = Employee::withoutGlobalScope(VisibleToCurrentUser::class)
            ->whereIn('id', array_filter([$this->form['supervisor_id'] ?? null, $this->form['dotted_line_manager_id'] ?? null]))
            ->get(['id', 'code', 'first_name', 'last_name']);
        foreach ($current as $e) {
            $people[$e->id] ??= "{$e->name} ({$e->code})";
        }

        return $people;
    }

    /** @return array<int, string> */
    private function orgUnits(): array
    {
        return app(EmployeeVisibility::class)->orgUnitOptionsFor(Auth::user());
    }

    protected function view(): string
    {
        return 'hrm::livewire.employees.job-form';
    }
}
