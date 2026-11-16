<?php

namespace App\Modules\Payroll\Exports;

use App\Modules\Hrm\Enums\EmployeeStatus;
use App\Modules\Hrm\Exports\TemplateSheet;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Imports\SalaryImporter;
use App\Modules\Payroll\Models\EmployeeSalary;
use App\Modules\Payroll\Models\SalaryProfile;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * The Excel template for importing salaries. Two sheets:
 *   "Salaries"  one row per employee the user may see (still employed): code, name, current profile and gross;
 *               fill "Effective from" + Gross / Basic or the head columns on the rows to change
 *   "Lists"     the salary profiles and the heads
 * Only the first sheet is imported.
 */
class SalaryImportTemplate implements Export, WithMultipleSheets
{
    /** @return array<int, object> */
    public function sheets(): array
    {
        $heads = SalaryImporter::headColumns();
        $headings = [...array_column(SalaryImporter::COLUMNS, 0), ...array_map([SalaryImporter::class, 'headHeading'], array_values($heads))];

        // Employees the user may see (VisibleToCurrentUser applies), with today's salary rows.
        $employees = Employee::query()
            ->whereIn('status', [EmployeeStatus::Active, EmployeeStatus::Suspended])
            ->with('salaryProfile')->orderBy('code')->get();
        $current = EmployeeSalary::query()->whereIn('employee_id', $employees->modelKeys())->effectiveOn(today())->with('head')->get()
            ->groupBy('employee_id');

        $rows = $employees->map(fn (Employee $e) => [
            $e->code,
            $e->name,
            isset($current[$e->id]) ? EmployeeSalary::breakdown($current[$e->id])->gross->toDecimal() : '',
            '',
            $e->salaryProfile->code ?? '',
        ])->all();

        $profiles = SalaryProfile::query()->where('is_active', true)->orderBy('name')->get();
        $lists = [];
        $headList = array_values($heads);
        for ($i = 0, $max = max($profiles->count(), count($headList)); $i < $max; $i++) {
            $p = $profiles[$i] ?? null;
            $h = $headList[$i] ?? null;
            $lists[] = [
                $p->code ?? '', $p->name ?? '', $p ? $p->summary() : '', '',
                $h ? SalaryImporter::headHeading($h) : '', $h->name ?? '', $h ? ($h->is_basic ? 'Basic' : $h->type->label()) : '',
            ];
        }

        return [
            new TemplateSheet('Salaries', $headings, $rows),
            new TemplateSheet('Lists', ['Profile code', 'Profile', 'Heads', '', 'Column', 'Head', 'Type'], $lists),
        ];
    }
}
