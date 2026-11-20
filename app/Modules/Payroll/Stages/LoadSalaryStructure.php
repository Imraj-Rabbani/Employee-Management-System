<?php

namespace App\Modules\Payroll\Stages;

use App\Modules\Payroll\Data\PayrollContext;
use App\Modules\Payroll\Exceptions\PayrollException;
use App\Modules\Payroll\Models\EmployeeSalary;
use Carbon\CarbonImmutable;
use Closure;

/**
 * Stage 2. The employee's salary (the set of EmployeeSalary rows) valid on the last day they work in the period.
 *
 * A salary that starts INSIDE the period (an increment on the 16th) is used for the whole period, with a warning:
 * splitting the period day by day belongs to increments (Phase 11). Salary changes made for earlier months are
 * arrears (10.7).
 */
class LoadSalaryStructure
{
    public function handle(PayrollContext $ctx, Closure $next): mixed
    {
        $rows = EmployeeSalary::query()
            ->where('employee_id', $ctx->employee->id)
            ->effectiveOn($ctx->workTo)
            ->with('head')
            ->get();

        if ($rows->isEmpty()) {
            throw new PayrollException(__('No salary on :date. Set it on the employee\'s Salary tab.', ['date' => format_date($ctx->workTo)]));
        }
        if (! $rows->contains(fn (EmployeeSalary $row) => $row->head->is_basic)) {
            throw new PayrollException(__('The salary valid on :date has no basic.', ['date' => format_date($ctx->workTo)]));
        }

        $ctx->salary = $rows;
        $ctx->salaryFrom = CarbonImmutable::parse($rows->max(fn (EmployeeSalary $row) => $row->effective_from->toDateString()));

        if ($ctx->salaryFrom->gt($ctx->workFrom)) {
            $ctx->warn(__('The salary changed on :date: the new salary is used for the whole period.', ['date' => format_date($ctx->salaryFrom)]));
        }

        return $next($ctx);
    }
}
