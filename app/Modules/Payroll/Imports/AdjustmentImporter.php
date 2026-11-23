<?php

namespace App\Modules\Payroll\Imports;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Hrm\Services\EmployeeVisibility;
use App\Modules\Payroll\Actions\RequestAdjustment;
use App\Modules\Payroll\Models\PayrollAdjustment;
use App\Modules\Payroll\Models\SalaryHead;
use App\Modules\Workflow\Exceptions\WorkflowException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Payroll adjustments from Excel / CSV (Payroll → Adjustments → Import). One row = one adjustment:
 *
 *     employee_code | head | year | month | amount | reason
 *
 * head = the salary head's code or name. Every row goes through RequestAdjustment (same rules as the form) and is
 * sent for approval. ALL OR NOTHING: every row is checked first, and the saving runs in ONE transaction, so a
 * workflow problem on row 40 saves nothing either. Small files (MAX_ROWS), so it runs right away, not queued.
 *
 *     $created = $importer->run($absolutePath, $user);   null → $importer->errors() lists {row, column, message}
 */
class AdjustmentImporter
{
    public const MAX_ROWS = 1000;

    public const COLUMNS = ['employee_code', 'head', 'year', 'month', 'amount', 'reason'];

    /** @var list<array{row: int, column: string, message: string}> */
    private array $errors = [];

    public function __construct(
        private RequestAdjustment $action,
        private EmployeeVisibility $visibility,
    ) {}

    /** @return list<array{row: int, column: string, message: string}> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return int|null how many adjustments were made; null = nothing saved, see errors() */
    public function run(string $absolutePath, User $user): ?int
    {
        $this->errors = [];
        $sheets = Excel::toArray(new class implements Import, WithHeadingRow {}, $absolutePath);

        $rows = [];
        foreach ($sheets[0] ?? [] as $i => $row) {
            if (collect($row)->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty()) {
                $rows[$i + 2] = $row;   // Excel row number: headings are row 1
            }
        }

        return $this->import($rows, $user);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows  keyed by Excel row number
     */
    public function import(array $rows, User $user): ?int
    {
        $this->errors = [];
        if ($rows === []) {
            return $this->fail(0, '', __('The file has no rows.'));
        }
        if (count($rows) > self::MAX_ROWS) {
            return $this->fail(0, '', __('At most :max rows per file. Split the file and import the parts one by one.', ['max' => self::MAX_ROWS]));
        }
        $missing = array_diff(self::COLUMNS, array_keys(reset($rows)));
        if ($missing !== []) {
            return $this->fail(1, '', __('Missing column(s): :columns. Use the template.', ['columns' => implode(', ', $missing)]));
        }

        $allowed = $this->visibility->entityIdsFor($user);   // null = everyone
        $codes = array_map(fn ($r) => trim((string) $r['employee_code']), $rows);
        $employees = Employee::withoutGlobalScope(VisibleToCurrentUser::class)->whereIn('code', $codes)->with('currentEntityAssignments')->get()
            ->keyBy(fn (Employee $e) => Str::lower($e->code));
        $heads = [];
        foreach (SalaryHead::all() as $head) {
            $heads[Str::lower($head->code)] = $head;
            $heads[Str::lower($head->name)] ??= $head;
        }

        $prepared = [];
        foreach ($rows as $line => $row) {
            $employee = $employees[Str::lower(trim((string) $row['employee_code']))] ?? null;
            if ($employee === null || ($allowed !== null && array_intersect($employee->entityIds(), $allowed) === [])) {
                $this->error($line, 'employee_code', __('Unknown employee code (or not one of your employees).'));

                continue;
            }
            $head = $heads[Str::lower(trim((string) $row['head']))] ?? null;
            if ($head === null) {
                $this->error($line, 'head', __('Unknown salary head.'));

                continue;
            }
            $values = [
                'year' => (string) $row['year'], 'month' => (string) $row['month'],
                'amount' => (string) $row['amount'], 'reason' => (string) $row['reason'],
            ];
            if ($problem = $this->action->problem($employee, $head, $values)) {
                $this->error($line, $problem['field'] === 'salary_head_id' ? 'head' : $problem['field'], $problem['message']);

                continue;
            }
            $prepared[$line] = [$employee, $head, $values];
        }

        if ($this->errors !== []) {
            return null;
        }

        try {
            DB::connection((new PayrollAdjustment)->getConnectionName())->transaction(function () use ($prepared) {
                foreach ($prepared as $line => [$employee, $head, $values]) {
                    try {
                        $this->action->handle($employee, $head, $values);
                    } catch (WorkflowException $e) {
                        $this->error($line, '', $e->getMessage());

                        throw $e;   // undo every row
                    }
                }
            });
        } catch (WorkflowException) {
            return null;
        }

        return count($prepared);
    }

    private function fail(int $row, string $column, string $message): null
    {
        $this->error($row, $column, $message);

        return null;
    }

    private function error(int $row, string $column, string $message): void
    {
        $this->errors[] = ['row' => $row, 'column' => $column, 'message' => $message];
    }
}
