<?php

namespace App\Modules\Payroll\Services;

use App\Core\Support\Money;
use App\Modules\Hrm\Contracts\SalaryBenchmarks;
use App\Modules\Hrm\Enums\EmployeeStatus;
use App\Modules\Payroll\Enums\HeadType;
use App\Modules\Payroll\Models\EmployeeSalary;
use Illuminate\Support\Facades\DB;

/**
 * Current monthly gross of the employees (still employed) in a grade or designation: count, min, max, average.
 * Company-wide on purpose (not limited to the entities the user may see): only totals come out, and only from
 * MIN_PEOPLE people up.
 */
class ComparableSalaries implements SalaryBenchmarks
{
    public function grossRange(string $column, int $id): array
    {
        // One row per employee: the sum of today's earnings counted in gross.
        $perEmployee = EmployeeSalary::query()
            ->effectiveOn(today())
            ->join('salary_heads', 'salary_heads.id', '=', 'employee_salaries.salary_head_id')
            ->join('employees', 'employees.id', '=', 'employee_salaries.employee_id')
            ->where('salary_heads.type', HeadType::Earning->value)
            ->where('salary_heads.in_gross', true)
            ->where("employees.{$column}", $id)
            ->whereIn('employees.status', [EmployeeStatus::Active->value, EmployeeStatus::Suspended->value])
            ->groupBy('employee_salaries.employee_id')
            ->selectRaw('SUM(employee_salaries.amount) as gross')
            ->toBase();

        $stats = DB::connection('tenant')->query()->fromSub($perEmployee, 'g')
            ->selectRaw('COUNT(*) as people, MIN(gross) as min_gross, MAX(gross) as max_gross, AVG(gross) as avg_gross')
            ->first();

        $count = (int) ($stats->people ?? 0);
        if ($count < self::MIN_PEOPLE) {
            return ['count' => $count, 'min' => null, 'max' => null, 'average' => null];
        }

        return [
            'count' => $count,
            'min' => Money::of((string) $stats->min_gross)->toDecimal(),
            'max' => Money::of((string) $stats->max_gross)->toDecimal(),
            'average' => Money::of((string) $stats->avg_gross)->toDecimal(),
        ];
    }
}
