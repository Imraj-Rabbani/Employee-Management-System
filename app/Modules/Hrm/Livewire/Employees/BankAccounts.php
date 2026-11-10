<?php

namespace App\Modules\Hrm\Livewire\Employees;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeBankAccount;
use Illuminate\Validation\Rule;

/**
 * Profile → Bank: the employee's accounts. [EMP-01]
 *
 * Exactly one is primary as soon as there is any account (salary goes there, Phase 12):
 *   - the first account becomes primary by itself
 *   - marking one primary un-marks the others
 *   - deleting the primary makes the next one primary
 *   - un-ticking the only primary has no effect: mark another account instead
 */
class BankAccounts extends EmployeeRecords
{
    protected function relation(): string
    {
        return 'bankAccounts';
    }

    protected function defaults(): array
    {
        return ['bank_name' => '', 'branch_name' => '', 'routing_no' => '', 'account_name' => '', 'account_no' => '', 'is_primary' => false];
    }

    protected function fieldRules(): array
    {
        return [
            'bank_name' => ['required', 'string', 'max:150'],
            'branch_name' => ['nullable', 'string', 'max:150'],
            'routing_no' => ['nullable', 'digits:9'],
            'account_name' => ['nullable', 'string', 'max:200'],
            // unique per employee: the same number may exist for someone else (a joint account).
            'account_no' => ['required', 'string', 'max:50',
                Rule::unique(EmployeeBankAccount::class, 'account_no')->where('employee_id', $this->employeeId)->ignore($this->recordId)],
            'is_primary' => ['boolean'],
        ];
    }

    protected function labels(): array
    {
        return ['bank_name' => __('bank'), 'routing_no' => __('routing number'), 'account_no' => __('account number')];
    }

    protected function fromForm(array $values): array
    {
        $values['is_primary'] = (bool) $values['is_primary'];

        return $values;
    }

    protected function saved(Employee $employee, TenantModel $record): void
    {
        /** @var EmployeeBankAccount $record */
        if ($record->is_primary) {
            // Row by row (not one mass UPDATE), so each change is written to the activity log.
            $employee->bankAccounts()->whereKeyNot($record->id)->where('is_primary', true)->get()
                ->each(fn (EmployeeBankAccount $other) => $other->update(['is_primary' => false]));
        }
        $this->ensurePrimary($employee, $record->id);
    }

    protected function deleted(Employee $employee): void
    {
        $this->ensurePrimary($employee);
    }

    /** No primary left? Make $preferId (or else the oldest account) primary. */
    private function ensurePrimary(Employee $employee, ?int $preferId = null): void
    {
        if ($employee->bankAccounts()->where('is_primary', true)->exists()) {
            return;
        }
        $id = $preferId ?? $employee->bankAccounts()->reorder()->orderBy('id')->value('id');
        if ($id !== null) {
            $employee->bankAccounts()->findOrFail($id)->update(['is_primary' => true]);
        }
    }

    protected function viewData(): array
    {
        return ['paymentMethod' => $this->employee()->payment_method?->label()];
    }

    protected function view(): string
    {
        return 'hrm::livewire.employees.bank-accounts';
    }
}
