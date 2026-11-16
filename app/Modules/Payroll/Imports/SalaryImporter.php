<?php

namespace App\Modules\Payroll\Imports;

use App\Core\Support\Money;
use App\Core\Support\SpreadsheetDate;
use App\Core\Support\TenantUploads;
use App\Models\User;
use App\Modules\Hrm\Enums\ImportStatus;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Hrm\Services\EmployeeVisibility;
use App\Modules\Payroll\Actions\SetEmployeeSalary;
use App\Modules\Payroll\Data\SalaryBreakdown;
use App\Modules\Payroll\Data\SalaryLine;
use App\Modules\Payroll\Enums\CalcMethod;
use App\Modules\Payroll\Enums\SalaryBasis;
use App\Modules\Payroll\Exceptions\SalaryProfileException;
use App\Modules\Payroll\Models\SalaryHead;
use App\Modules\Payroll\Models\SalaryImport;
use App\Modules\Payroll\Models\SalaryProfile;
use App\Modules\Payroll\Services\SalaryProfileCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Throwable;

/**
 * Imports employee salaries from Excel (the template from SalaryImportTemplate). [PC-03]
 *
 *     app(SalaryImporter::class)->run($import);   reads $import->file_path, fills status / counts / errors
 *
 * One row = one employee. Only rows with "Effective from" are imported; rows without it are left out, so the
 * template (every employee) can be filled in for just a few. Per row, either:
 *   - Gross OR Basic: the salary profile (column, else the employee's own) is applied (SalaryProfileCalculator);
 *     a filled head column then overrides that head, like typing over it on the Salary tab
 *   - neither: the head columns ARE the salary (the basic column is needed)
 * Saved through SetEmployeeSalary, so the same rules as the Salary tab (date, basic, ...) apply; a filled
 * salary profile becomes the employee's profile.
 *
 * ALL OR NOTHING, like the employee import: every row is checked first; one error anywhere → nothing is saved and
 * $import->errors lists every problem as {row, column, message}. Employees are limited to the uploader's org units.
 */
class SalaryImporter
{
    public const MAX_ROWS = 5000;

    /** Fixed columns: key => [heading, help]. The head columns ("Head HR" → head_hr) follow them. */
    public const COLUMNS = [
        'employee_code' => ['Employee code *', ''],
        'employee_name' => ['Employee name', 'Not imported, only to help you'],
        'current_gross' => ['Current gross', 'Not imported, only to help you'],
        'effective_from' => ['Effective from', 'YYYY-MM-DD. Empty = row left out'],
        'salary_profile' => ['Salary profile', 'Code or name. Empty = the employee\'s own'],
        'gross' => ['Gross', 'Apply the profile to this gross'],
        'basic' => ['Basic', 'Or apply it to this basic'],
    ];

    /** @var list<array{row: int, column: string, message: string}> */
    private array $errors = [];

    public function __construct(
        private SalaryProfileCalculator $calculator,
        private SetEmployeeSalary $setSalary,
    ) {}

    /**
     * Heads that can be in an employee's salary, with their column key: head_basic => the basic head.
     *
     * @return array<string, SalaryHead>
     */
    public static function headColumns(): array
    {
        $heads = SalaryHead::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('is_basic', true)->orWhereNotIn('calc_method', [CalcMethod::Formula, CalcMethod::Attendance]))
            ->ordered()->get();

        $columns = [];
        foreach ($heads as $head) {
            $columns[self::headKey($head)] = $head;
        }

        return $columns;
    }

    public static function headHeading(SalaryHead $head): string
    {
        return 'Head '.$head->code;
    }

    /** What maatwebsite turns the heading into when the file is read: "Head HR" → "head_hr". */
    public static function headKey(SalaryHead $head): string
    {
        return HeadingRowFormatter::format([self::headHeading($head)])[0];
    }

    /** Runs a whole import and records the result on $import. Never throws for bad data. */
    public function run(SalaryImport $import): void
    {
        $import->update(['status' => ImportStatus::Processing, 'started_at' => now(), 'errors' => null]);

        if ($import->user === null) {
            $import->update([
                'status' => ImportStatus::Failed,
                'errors' => [['row' => 0, 'column' => '', 'message' => __('The user who uploaded this file no longer exists.')]],
                'finished_at' => now(),
            ]);

            return;
        }

        try {
            $rows = $this->read(app(TenantUploads::class)->path($import->file_path));
            $prepared = $this->check($rows, $import->user);
            $failed = $this->errors !== [];

            if (! $failed) {
                $this->import($prepared);
            }

            $import->update([
                'status' => $failed ? ImportStatus::Failed : ImportStatus::Done,
                'total_rows' => count($rows),
                'imported_rows' => $failed ? 0 : count($prepared),
                'errors' => $failed ? $this->errors : null,
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
            $import->update([
                'status' => ImportStatus::Failed,
                'errors' => [['row' => 0, 'column' => '', 'message' => __('The file could not be read: :error', ['error' => $e->getMessage()])]],
                'finished_at' => now(),
            ]);
        }
    }

    /**
     * The rows with an employee code, keyed by their Excel row number (headings are row 1).
     *
     * @return array<int, array<string, mixed>>
     */
    public function read(string $absolutePath): array
    {
        $sheets = Excel::toArray(new class implements Import, WithHeadingRow {}, $absolutePath);
        $rows = [];
        foreach ($sheets[0] ?? [] as $i => $row) {
            if (trim((string) ($row['employee_code'] ?? '')) !== '') {
                $rows[$i + 2] = $row;
            }
        }

        return $rows;
    }

    /**
     * Checks every row. Returns what to save per row number (only rows with "Effective from").
     * Problems are collected in errors().
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{employee: Employee, from: CarbonImmutable, salary: SalaryBreakdown, profile: ?SalaryProfile}>
     */
    public function check(array $rows, ?User $uploader): array
    {
        $this->errors = [];

        if ($rows === []) {
            $this->error(0, '', __('The file has no employee rows.'));

            return [];
        }
        if (count($rows) > self::MAX_ROWS) {
            $this->error(0, '', __('At most :max rows per file. Split the file and import the parts one by one.', ['max' => self::MAX_ROWS]));

            return [];
        }
        if (! array_key_exists('effective_from', reset($rows))) {
            $this->error(1, '', __('Missing column: Effective from. Use the template.'));

            return [];
        }

        $heads = self::headColumns();
        $basicHead = SalaryHead::basic();
        $profiles = $this->profileLookup();
        $allowedUnits = $uploader ? app(EmployeeVisibility::class)->orgUnitIdsFor($uploader) : null;

        // Every code company-wide (codes are unique), then the uploader's org units are checked per row.
        $codes = array_map(fn ($r) => trim((string) $r['employee_code']), $rows);
        $employees = Employee::withoutGlobalScope(VisibleToCurrentUser::class)->whereIn('code', $codes)->get()
            ->keyBy(fn (Employee $e) => Str::lower($e->code));

        $seen = [];
        $prepared = [];

        foreach ($rows as $n => $row) {
            $text = fn (string $key) => ($v = trim((string) ($row[$key] ?? ''))) === '' ? null : $v;
            $code = (string) $text('employee_code');

            try {
                $from = SpreadsheetDate::parse($row['effective_from'] ?? null);
            } catch (InvalidArgumentException $e) {
                $this->error($n, 'effective_from', $e->getMessage());

                continue;
            }
            if ($from === null) {
                continue;   // no change for this employee
            }

            $employee = $employees[Str::lower($code)] ?? null;
            if ($employee === null || ($allowedUnits !== null && ! in_array($employee->org_unit_id, $allowedUnits, true))) {
                $this->error($n, 'employee_code', __('No employee with code :code (or not in your org units).', ['code' => $code]));

                continue;
            }
            if (isset($seen[$employee->id])) {
                $this->error($n, 'employee_code', __(':code is also on row :row. One salary per employee per file.', ['code' => $code, 'row' => $seen[$employee->id]]));

                continue;
            }
            $seen[$employee->id] = $n;

            // Amounts typed in head columns. A bad one stops the row (the follow-up messages would only confuse).
            $typed = [];
            $badAmount = false;
            foreach ($heads as $key => $head) {
                $value = $row[$key] ?? null;
                if ($value === null || trim((string) $value) === '') {
                    continue;
                }
                if (! is_numeric(str_replace(',', '', (string) $value)) || (float) str_replace(',', '', (string) $value) < 0) {
                    $this->error($n, $key, __(':value is not an amount of 0 or more.', ['value' => (string) $value]));
                    $badAmount = true;

                    continue;
                }
                $typed[$head->id] = Money::of((string) $value);
            }
            if ($badAmount) {
                continue;
            }

            $profile = null;
            if (($name = $text('salary_profile')) !== null) {
                $profile = $profiles[Str::lower($name)] ?? null;
                if ($profile === null) {
                    $this->error($n, 'salary_profile', __('“:value” was not found.', ['value' => $name]));

                    continue;
                }
            }

            $gross = $text('gross');
            $basic = $text('basic');
            if ($gross !== null && $basic !== null) {
                $this->error($n, 'gross', __('Fill Gross OR Basic, not both.'));

                continue;
            }

            if ($gross !== null || $basic !== null) {
                $column = $gross !== null ? 'gross' : 'basic';
                $amount = (string) ($gross ?? $basic);
                $applyProfile = $profile ?? ($employee->salary_profile_id ? SalaryProfile::find($employee->salary_profile_id) : null);
                if ($applyProfile === null) {
                    $this->error($n, 'salary_profile', __('Give a salary profile to apply (the employee has none).'));

                    continue;
                }
                if (! is_numeric(str_replace(',', '', $amount))) {
                    $this->error($n, $column, __(':value is not an amount.', ['value' => $amount]));

                    continue;
                }
                try {
                    $applied = $this->calculator->forProfile($applyProfile, $column === 'gross' ? SalaryBasis::Gross : SalaryBasis::Basic, Money::of($amount));
                } catch (SalaryProfileException $e) {
                    $this->error($n, $column, $e->getMessage());

                    continue;
                }
                $profile ??= $applyProfile;
                $salary = $this->override($applied, $typed, $heads);
            } else {
                if ($typed === []) {
                    $this->error($n, 'gross', __('Fill Gross, Basic or the head columns.'));

                    continue;
                }
                $salary = $this->override(SalaryBreakdown::fromLines([]), $typed, $heads);
            }

            $date = CarbonImmutable::parse($from);
            if ($problem = $this->setSalary->problem($employee, $date, $salary)) {
                $column = match ($problem['field']) {
                    'effective_from' => 'effective_from',
                    default => $basicHead ? self::headKey($basicHead) : '',
                };
                $this->error($n, $column, $problem['message']);

                continue;
            }

            $prepared[$n] = ['employee' => $employee, 'from' => $date, 'salary' => $salary, 'profile' => $profile];
        }

        usort($this->errors, fn ($a, $b) => $a['row'] <=> $b['row']);

        return $prepared;
    }

    /** @return list<array{row: int, column: string, message: string}> */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Saves every salary in ONE transaction.
     *
     * @param  array<int, array{employee: Employee, from: CarbonImmutable, salary: SalaryBreakdown, profile: ?SalaryProfile}>  $prepared
     */
    public function import(array $prepared): void
    {
        DB::connection((new Employee)->getConnectionName())->transaction(function () use ($prepared) {
            foreach ($prepared as $row) {
                $this->setSalary->handle($row['employee'], $row['from'], $row['salary'], $row['profile']);
            }
        });
    }

    // ---- helpers ---------------------------------------------------------------------------

    /**
     * The salary with the typed head amounts put in (a typed amount is a plain amount, not "50% of basic").
     *
     * @param  array<int, Money>  $typed  head id => amount
     * @param  array<string, SalaryHead>  $heads
     */
    private function override(SalaryBreakdown $salary, array $typed, array $heads): SalaryBreakdown
    {
        $byId = [];
        foreach ($heads as $head) {
            $byId[$head->id] = $head;
        }

        $lines = [];
        foreach ($salary->lines as $line) {
            if (! array_key_exists($line->head->id, $typed)) {
                $lines[] = $line;
            }
        }
        foreach ($typed as $headId => $amount) {
            $lines[] = new SalaryLine($byId[$headId], '', $amount);
        }

        return SalaryBreakdown::fromLines($lines);
    }

    /** @return array<string, SalaryProfile> code / name in lower case => profile */
    private function profileLookup(): array
    {
        /** @var Collection<int, SalaryProfile> $profiles */
        $profiles = SalaryProfile::all();
        $map = [];
        foreach ($profiles as $p) {
            $map[Str::lower($p->code)] = $p;
            $map[Str::lower($p->name)] = $p;
        }

        return $map;
    }

    private function error(int $row, string $column, string $message): void
    {
        $this->errors[] = ['row' => $row, 'column' => $column, 'message' => $message];
    }
}
