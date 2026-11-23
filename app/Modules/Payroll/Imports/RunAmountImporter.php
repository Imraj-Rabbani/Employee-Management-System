<?php

namespace App\Modules\Payroll\Imports;

use App\Core\Support\Money;
use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Services\EmployeeVisibility;
use App\Modules\Payroll\Enums\HeadType;
use App\Modules\Payroll\Enums\PayrollCalcStatus;
use App\Modules\Payroll\Enums\PayrollRunType;
use App\Modules\Payroll\Models\PayrollItem;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\PayrollRunAmount;
use App\Modules\Payroll\Models\SalaryHead;
use App\Modules\Payroll\Services\PayrollRunScope;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

/**
 * The amounts of an upload run (off-cycle, or head-wise with uploaded amounts) from Excel / CSV:
 *
 *     employee_code | head | amount | note          (head: code or name; a head-wise run's own head when empty)
 *
 * Other columns (e.g. employee_name in the template) are ignored. ALL OR NOTHING: every row is checked, then the
 * upload REPLACES the run's amounts in one transaction. The run's employees follow the file (PayrollRunScope::sync)
 * and everyone's numbers are cleared: calculate the run afterwards.
 *
 *     $count = $importer->run($path, $run, $user);   null → $importer->errors() lists {row, column, message}
 *
 * Per row: an employee of the run's entity the uploader may see, employed in the period; an active earning or
 * deduction head (head-wise: the run's head); amount > 0 with ≤ 2 decimals; each employee and head once; for a
 * head-wise run, not already in another run of that head for an overlapping period.
 */
class RunAmountImporter
{
    public const MAX_ROWS = 5000;

    /** Required columns ("head" is optional for a head-wise run). */
    public const COLUMNS = ['employee_code', 'head', 'amount', 'note'];

    /** @var list<array{row: int, column: string, message: string}> */
    private array $errors = [];

    public function __construct(
        private PayrollRunScope $scope,
        private EmployeeVisibility $visibility,
    ) {}

    /** @return list<array{row: int, column: string, message: string}> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function run(string $absolutePath, PayrollRun $run, User $user): ?int
    {
        $sheets = Excel::toArray(new class implements Import, WithHeadingRow {}, $absolutePath);
        $rows = [];
        foreach ($sheets[0] ?? [] as $i => $row) {
            if (collect($row)->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty()) {
                $rows[$i + 2] = $row;
            }
        }

        return $this->import($rows, $run, $user);
    }

    /** @param  array<int, array<string, mixed>>  $rows  keyed by Excel row number */
    public function import(array $rows, PayrollRun $run, User $user): ?int
    {
        $this->errors = [];
        if (! $run->isUploadRun() || ! $run->status->isEditable()) {
            return $this->fail(0, '', __('Amounts can be uploaded only to an upload run that is not submitted.'));
        }
        if ($rows === []) {
            return $this->fail(0, '', __('The file has no rows.'));
        }
        if (count($rows) > self::MAX_ROWS) {
            return $this->fail(0, '', __('At most :max rows per file.', ['max' => self::MAX_ROWS]));
        }
        $required = $run->run_type === PayrollRunType::HeadWise ? ['employee_code', 'amount'] : ['employee_code', 'head', 'amount'];
        $missing = array_diff($required, array_keys(reset($rows)));
        if ($missing !== []) {
            return $this->fail(1, '', __('Missing column(s): :columns. Use the template.', ['columns' => implode(', ', $missing)]));
        }

        $allowed = $this->visibility->entityIdsFor($user);
        $inScope = $this->scope->employees($run->period(), $run->entity_id)->with('currentEntityAssignments')->get()
            ->keyBy(fn (Employee $e) => Str::lower($e->code));
        $known = Employee::withoutGlobalScopes()->whereIn('code', array_map(fn ($r) => trim((string) $r['employee_code']), $rows))->pluck('code')
            ->map(fn ($c) => Str::lower($c))->flip();
        $elsewhere = array_flip($this->scope->paidElsewhere($run));
        $heads = [];
        foreach (SalaryHead::all() as $head) {
            $heads[Str::lower($head->code)] = $head;
            $heads[Str::lower($head->name)] ??= $head;
        }

        $prepared = [];
        $seen = [];
        foreach ($rows as $line => $row) {
            $code = Str::lower(trim((string) $row['employee_code']));
            $employee = $inScope[$code] ?? null;
            if ($employee === null || ($allowed !== null && array_intersect($employee->entityIds(), $allowed) === [])) {
                $this->error($line, 'employee_code', isset($known[$code])
                    ? __('Not employed in :period, or not in this run\'s scope.', ['period' => $run->period()->label()])
                    : __('Unknown employee code.'));

                continue;
            }

            $typed = Str::lower(trim((string) ($row['head'] ?? '')));
            $head = $typed === '' ? ($run->run_type === PayrollRunType::HeadWise ? $run->head : null) : ($heads[$typed] ?? null);
            if ($head === null) {
                $this->error($line, 'head', __('Unknown salary head.'));

                continue;
            }
            if ($run->run_type === PayrollRunType::HeadWise && $head->id !== $run->salary_head_id) {
                $this->error($line, 'head', __('This run pays :head only.', ['head' => $run->head->name]));

                continue;
            }
            if (! $head->is_active || $head->is_basic || $head->type === HeadType::Info) {
                $this->error($line, 'head', __('Choose an active earning or deduction head (not the basic).'));

                continue;
            }

            $amount = str_replace(',', '', trim((string) $row['amount']));
            if (preg_match('/^\d+(\.\d{1,2})?$/', $amount) !== 1 || ! Money::of($amount)->isPositive()) {
                $this->error($line, 'amount', __('Amount: a number more than 0, at most 2 decimals.'));

                continue;
            }
            if (isset($seen[$employee->id][$head->id])) {
                $this->error($line, 'employee_code', __(':code has :head twice (row :row).', ['code' => $employee->code, 'head' => $head->code, 'row' => $seen[$employee->id][$head->id]]));

                continue;
            }
            if (isset($elsewhere[$employee->id])) {
                $this->error($line, 'employee_code', __(':code is already in another :head run for this period.', ['code' => $employee->code, 'head' => $head->name]));

                continue;
            }

            $seen[$employee->id][$head->id] = $line;
            $prepared[] = ['employee_id' => $employee->id, 'salary_head_id' => $head->id, 'amount' => Money::of($amount)->toDecimal(), 'note' => trim((string) ($row['note'] ?? '')) ?: null];
        }

        if ($this->errors !== []) {
            return null;
        }

        $run->getConnection()->transaction(function () use ($run, $prepared) {
            PayrollRunAmount::where('payroll_run_id', $run->id)->delete();
            $now = now();
            foreach (array_chunk($prepared, 500) as $chunk) {
                PayrollRunAmount::insert(array_map(fn (array $r) => ['payroll_run_id' => $run->id, 'created_at' => $now, 'updated_at' => $now, ...$r], $chunk));
            }

            $this->scope->sync($run);
            // New amounts: the old numbers are void until the run is calculated again.
            PayrollItem::where('payroll_run_id', $run->id)->delete();
            $run->employees()->update([
                'calc_status' => PayrollCalcStatus::Pending->value, 'error' => null, 'warnings' => null,
                'basic' => 0, 'gross' => 0, 'deductions' => 0, 'net' => 0,
            ]);
            $run->refreshTotals();
        });

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
