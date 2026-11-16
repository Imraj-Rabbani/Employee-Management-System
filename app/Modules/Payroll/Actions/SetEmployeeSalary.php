<?php

namespace App\Modules\Payroll\Actions;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Data\SalaryBreakdown;
use App\Modules\Payroll\Enums\CalcMethod;
use App\Modules\Payroll\Models\EmployeeSalary;
use App\Modules\Payroll\Models\SalaryProfile;
use Carbon\CarbonImmutable;

/**
 * Gives an employee a new salary from a date: a set of heads with amounts.
 *
 *     $action->problem($employee, $from, $salary)    null, or ['field' => ..., 'message' => ...]
 *     $action->handle($employee, $from, $salary, $profile)
 *
 * Effective-dated, never overwritten: the salary open on that day is CLOSED the day before (effective_to) and the new
 * rows start. Only from the latest start onwards: inserting a salary between two older ones is refused. The SAME
 * start date as the latest = a correction: those rows are replaced.
 * Heads with amount 0 are left out. ONE readable entry in the employee's history.
 *
 * Not checked yet: payroll periods already paid (the payroll run will lock them).
 */
class SetEmployeeSalary
{
    /** @return array{field: string, message: string}|null */
    public function problem(Employee $employee, CarbonImmutable $from, SalaryBreakdown $salary): ?array
    {
        $latest = EmployeeSalary::where('employee_id', $employee->id)->max('effective_from');

        $message = match (true) {
            $from->lt($employee->join_date) => __('The salary cannot start before the joining date (:date).', ['date' => format_date($employee->join_date)]),
            $employee->separation_date !== null && $from->gt($employee->separation_date) => __('The employee left on :date.', ['date' => format_date($employee->separation_date)]),
            $latest !== null && $from->lt(CarbonImmutable::parse($latest)) => __('A salary already starts on :date. A new salary can start on that day (to correct it) or later.', ['date' => format_date($latest)]),
            default => null,
        };
        if ($message !== null) {
            return ['field' => 'effective_from', 'message' => $message];
        }

        foreach ($salary->lines as $line) {
            if ($line->amount->isNegative()) {
                return ['field' => 'lines', 'message' => __(':head cannot be negative.', ['head' => $line->head->name])];
            }
            if (in_array($line->head->calc_method, [CalcMethod::Formula, CalcMethod::Attendance], true) && ! $line->head->is_basic) {
                return ['field' => 'lines', 'message' => __(':head is worked out by the payroll run, not set per employee.', ['head' => $line->head->name])];
            }
        }

        if (! $salary->basic->isPositive()) {
            return ['field' => 'lines', 'message' => __('Give the basic salary.')];
        }

        return null;
    }

    public function handle(Employee $employee, CarbonImmutable $from, SalaryBreakdown $salary, ?SalaryProfile $profile = null): void
    {
        if ($problem = $this->problem($employee, $from, $salary)) {
            throw new \InvalidArgumentException($problem['message']);
        }

        $employee->getConnection()->transaction(function () use ($employee, $from, $salary, $profile) {
            // Lock the employee row: two people saving a salary at once would otherwise both close the same rows.
            Employee::withoutGlobalScopes()->whereKey($employee->id)->lockForUpdate()->first();

            $rows = EmployeeSalary::where('employee_id', $employee->id);
            $old = EmployeeSalary::breakdown($rows->clone()->effectiveOn($from)->with('head')->get());

            $rows->clone()->whereDate('effective_from', $from)->delete();                       // a correction
            $rows->clone()->whereNull('effective_to')->update(['effective_to' => $from->subDay()->toDateString()]);

            foreach ($salary->lines as $line) {
                if ($line->amount->isZero()) {
                    continue;
                }
                EmployeeSalary::create([
                    'employee_id' => $employee->id,
                    'salary_head_id' => $line->head->id,
                    'value_type' => $line->valueType,
                    'rate' => $line->rate,
                    'amount' => $line->amount,
                    'effective_from' => $from->toDateString(),
                    'salary_profile_id' => $profile?->id,
                ]);
            }

            if ($profile !== null && $employee->salary_profile_id !== $profile->id) {
                $employee->update(['salary_profile_id' => $profile->id]);   // logs its own entry
            }

            activity()->performedOn($employee)->event('updated')
                ->withChanges([
                    'old' => ['salary' => $old->isEmpty() ? null : $this->readable($old)],
                    'attributes' => ['salary' => __('from :date: :heads', ['date' => format_date($from), 'heads' => $this->readable($salary)])],
                ])
                ->log('updated');
        });
    }

    /** "BASIC 18,366.66 · HR 9,183.34 · gross 30,000.00" */
    private function readable(SalaryBreakdown $salary): string
    {
        $parts = [];
        foreach ($salary->lines as $line) {
            if (! $line->amount->isZero()) {
                $parts[] = $line->head->code.' '.$line->amount->format();
            }
        }
        $parts[] = __('gross :amount', ['amount' => $salary->gross->format()]);

        return implode(' · ', $parts);
    }
}
