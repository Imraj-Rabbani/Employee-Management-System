<?php

namespace App\Modules\Hrm\Livewire\Employees;

use App\Modules\Hrm\Enums\PaymentMethod;
use App\Modules\Hrm\Models\Branch;
use App\Modules\Hrm\Models\CostCenter;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmploymentType;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Hrm\Models\LeaveProfile;
use App\Modules\Hrm\Models\Position;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Payroll\Models\HolidayProfile;
use App\Modules\Payroll\Models\Shift;
use App\Modules\Payroll\Models\WeekendProfile;
use Illuminate\Validation\Rule;

/**
 * Profile → Job → Edit: code, placement, reporting lines, dates, payment method,
 * attendance: shift, weekend profile, holiday calendar (7.1), and the leave profile (8.1). [EMP-01, TA-01, TA-02, LV-02]
 *
 * Not here on purpose: status and separation (Lifecycle, Phase 13), and the org unit: that is changed on
 * the History tab (OrgHistory → ChangeOrgUnit) so every move is dated and kept. (5.4)
 *
 * Extra rules:
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
            'shift_id' => ['nullable', Rule::exists(Shift::class, 'id')],
            'has_shifting' => ['boolean'],
            'weekend_profile_id' => ['nullable', Rule::exists(WeekendProfile::class, 'id')],
            'holiday_profile_id' => ['nullable', Rule::exists(HolidayProfile::class, 'id')],
            'leave_profile_id' => ['nullable', Rule::exists(LeaveProfile::class, 'id')],
        ];
    }

    protected function labels(): array
    {
        return [
            'code' => __('employee code'), 'access_code' => __('attendance ID'),
            'position_id' => __('position'), 'cost_center_id' => __('cost center'), 'employment_type_id' => __('employment type'),
            'supervisor_id' => __('supervisor'), 'dotted_line_manager_id' => __('dotted-line manager'),
            'join_date' => __('join date'), 'planned_confirmation_date' => __('planned confirmation'),
            'confirmation_date' => __('confirmation date'), 'payment_method' => __('payment method'),
            'shift_id' => __('shift'), 'weekend_profile_id' => __('weekend profile'), 'holiday_profile_id' => __('holiday calendar'),
            'leave_profile_id' => __('leave profile'),
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
        return [
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
            // Active ones, plus the employee's current one even if it was made inactive (or the dropdown would clear it).
            'shifts' => Shift::where('is_active', true)->orWhere('id', $this->form['shift_id'] ?? null)->orderBy('name')->get()
                ->mapWithKeys(fn (Shift $s) => [$s->id => $s->optionLabel()])->all(),
            'defaultShift' => Shift::default()?->optionLabel(),
            'weekendProfiles' => WeekendProfile::where('is_active', true)->orWhere('id', $this->form['weekend_profile_id'] ?? null)
                ->orderBy('name')->pluck('name', 'id')->all(),
            'holidayProfiles' => HolidayProfile::where('is_active', true)->orWhere('id', $this->form['holiday_profile_id'] ?? null)
                ->orderBy('name')->pluck('name', 'id')->all(),
            'leaveProfiles' => LeaveProfile::where('is_active', true)->orWhere('id', $this->form['leave_profile_id'] ?? null)
                ->orderBy('name')->pluck('name', 'id')->all(),
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

    protected function view(): string
    {
        return 'hrm::livewire.employees.job-form';
    }
}
