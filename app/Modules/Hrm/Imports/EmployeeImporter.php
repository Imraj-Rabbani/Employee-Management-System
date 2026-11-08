<?php

namespace App\Modules\Hrm\Imports;

use App\Core\Support\TenantUploads;
use App\Models\User;
use App\Modules\Hrm\Enums\Gender;
use App\Modules\Hrm\Enums\ImportStatus;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeImport;
use App\Modules\Hrm\Models\EmploymentType;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Hrm\Services\EmployeeVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Imports new employees from an Excel file (the template from EmployeeImportTemplate). [EMP-06]
 *
 *     app(EmployeeImporter::class)->run($import);   reads $import->file_path, fills status / counts / errors
 *
 * ALL OR NOTHING: every row is checked first. One error anywhere → nothing is imported and $import->errors
 * lists every problem as {row, column, message}. The user fixes the file and uploads it again; no half
 * imports, no duplicates on the second try.
 *
 * Lookups (org unit, designation, grade, type) accept the CODE or the NAME, any upper/lower case.
 * Dates may be real Excel dates or text like 2024-01-31 / 31/01/2024.
 * The supervisor may be an existing employee or one further down in the same file.
 * Org units are limited to what the uploader may see (their role's org-unit scope).
 */
class EmployeeImporter
{
    public const MAX_ROWS = 5000;

    /**
     * The template's columns: key => [heading, help]. The heading row is read back as the key
     * (maatwebsite turns "Employee code *" into "employee_code"). * = required.
     */
    public const COLUMNS = [
        'employee_code' => ['Employee code *', 'Unique, e.g. E-0101'],
        'first_name' => ['First name *', ''],
        'last_name' => ['Last name', ''],
        'title' => ['Title', 'Mr, Ms, Dr'],
        'name_local' => ['Name local', 'e.g. the name in Bangla'],
        'gender' => ['Gender', 'Male or Female'],
        'birth_date' => ['Birth date', 'YYYY-MM-DD'],
        'join_date' => ['Join date *', 'YYYY-MM-DD'],
        'org_unit' => ['Org unit *', 'Code or name, see the Lists sheet'],
        'designation' => ['Designation', 'Code or name'],
        'grade' => ['Grade', 'Code or name'],
        'employment_type' => ['Employment type', 'Name'],
        'supervisor_code' => ['Supervisor code', 'Employee code of the supervisor'],
        'mobile' => ['Mobile', ''],
        'personal_email' => ['Personal email', ''],
        'office_email' => ['Office email', ''],
        'national_id' => ['National ID', ''],
        'tin' => ['TIN', ''],
    ];

    /** @var list<array{row: int, column: string, message: string}> */
    private array $errors = [];

    /** Runs a whole import and records the result on $import. Never throws for bad data. */
    public function run(EmployeeImport $import): void
    {
        $import->update(['status' => ImportStatus::Processing, 'started_at' => now(), 'errors' => null]);

        // No uploader = no way to know their org-unit access. Refuse rather than allow everything.
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
     * The data rows, keyed by their Excel row number (headings are row 1). Empty rows are skipped.
     *
     * @return array<int, array<string, mixed>>
     */
    public function read(string $absolutePath): array
    {
        // maatwebsite v4: an import is marked with the Import interface; WithHeadingRow = row 1 holds the
        // column names, so each row comes back as ['employee_code' => 'E-0101', ...].
        $sheets = Excel::toArray(new class implements Import, WithHeadingRow {}, $absolutePath);
        $rows = [];

        foreach ($sheets[0] ?? [] as $i => $row) {
            if (array_filter($row, fn ($v) => $v !== null && trim((string) $v) !== '') === []) {
                continue;
            }
            $rows[$i + 2] = $row;
        }

        return $rows;
    }

    /**
     * Checks every row. Returns employee attributes per row number, plus 'supervisor_code' to link later.
     * Problems are collected in $this->errors (see errors()).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
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
        $missing = array_diff(['employee_code', 'first_name', 'join_date', 'org_unit'], array_keys(reset($rows)));
        if ($missing !== []) {
            $this->error(1, '', __('Missing columns: :list. Use the template.', ['list' => implode(', ', $missing)]));

            return [];
        }

        $units = $this->lookup(OrgUnit::query()->get(['id', 'code', 'name']));
        $designations = $this->lookup(Designation::query()->get(['id', 'code', 'name']));
        $grades = $this->lookup(Grade::query()->get(['id', 'code', 'name']));
        $types = $this->lookup(EmploymentType::query()->get(['id', 'name']));
        $allowedUnits = $uploader ? app(EmployeeVisibility::class)->orgUnitIdsFor($uploader) : null;

        // Every existing code, also of employees the uploader cannot see: codes are unique company-wide.
        $existing = Employee::withoutGlobalScope(VisibleToCurrentUser::class)->pluck('id', 'code')
            ->mapWithKeys(fn ($id, $code) => [Str::lower((string) $code) => $id])->all();
        $codesInFile = [];
        foreach ($rows as $row) {
            $codesInFile[] = Str::lower(trim((string) ($row['employee_code'] ?? '')));
        }
        $seen = [];
        $prepared = [];

        foreach ($rows as $n => $row) {
            $text = fn (string $key) => ($v = trim((string) ($row[$key] ?? ''))) === '' ? null : $v;
            $code = $text('employee_code');

            $values = [
                'code' => $code,
                'first_name' => $text('first_name'),
                'last_name' => $text('last_name'),
                'title' => $text('title'),
                'name_local' => $text('name_local'),
                'mobile' => $text('mobile'),
                'personal_email' => $text('personal_email'),
                'office_email' => $text('office_email'),
                'national_id' => $text('national_id'),
                'tin' => $text('tin'),
                'gender' => $this->gender($n, $text('gender')),
                'birth_date' => $this->date($n, 'birth_date', $row['birth_date'] ?? null),
                'join_date' => $this->date($n, 'join_date', $row['join_date'] ?? null),
                'org_unit_id' => $this->find($n, 'org_unit', $text('org_unit'), $units),
                'designation_id' => $this->find($n, 'designation', $text('designation'), $designations),
                'grade_id' => $this->find($n, 'grade', $text('grade'), $grades),
                'employment_type_id' => $this->find($n, 'employment_type', $text('employment_type'), $types),
            ];

            // The usual field rules (same limits as the forms).
            $validator = Validator::make($values, [
                'code' => ['required', 'max:50'],
                'first_name' => ['required', 'max:100'],
                'last_name' => ['nullable', 'max:100'],
                'title' => ['nullable', 'max:20'],
                'name_local' => ['nullable', 'max:200'],
                'mobile' => ['nullable', 'max:30'],
                'personal_email' => ['nullable', 'email', 'max:255'],
                'office_email' => ['nullable', 'email', 'max:255'],
                'national_id' => ['nullable', 'max:30'],
                'tin' => ['nullable', 'max:30'],
            ]);
            foreach ($validator->errors()->messages() as $field => $messages) {
                $this->error($n, $field === 'code' ? 'employee_code' : $field, $messages[0]);
            }

            if ($values['join_date'] === null && $this->hasNoError($n, 'join_date')) {
                $this->error($n, 'join_date', __('Join date is required.'));
            }
            if ($values['org_unit_id'] === null && $this->hasNoError($n, 'org_unit')) {
                $this->error($n, 'org_unit', __('Org unit is required.'));
            }
            if ($values['org_unit_id'] !== null && $allowedUnits !== null && ! in_array($values['org_unit_id'], $allowedUnits, true)) {
                $this->error($n, 'org_unit', __('You may not add employees to this org unit.'));
            }

            if ($code !== null) {
                $key = Str::lower($code);
                if (isset($existing[$key])) {
                    $this->error($n, 'employee_code', __('Employee code :code already exists.', ['code' => $code]));
                } elseif (isset($seen[$key])) {
                    $this->error($n, 'employee_code', __('Employee code :code is also on row :row.', ['code' => $code, 'row' => $seen[$key]]));
                }
                $seen[$key] ??= $n;
            }

            $supervisor = $text('supervisor_code');
            if ($supervisor !== null) {
                $key = Str::lower($supervisor);
                if ($key === Str::lower((string) $code)) {
                    $this->error($n, 'supervisor_code', __('An employee cannot report to themselves.'));
                } elseif (! isset($existing[$key]) && ! in_array($key, $codesInFile, true)) {
                    $this->error($n, 'supervisor_code', __('No employee with code :code (here or in the file).', ['code' => $supervisor]));
                }
            }

            $prepared[$n] = [...$values, 'supervisor_code' => $supervisor];
        }

        // Supervisor loops inside the file (A → B → A). Existing employees cannot report to new ones,
        // so a loop can only run through rows of this file.
        $bossOf = [];
        foreach ($prepared as $n => $values) {
            if ($values['code'] !== null && $values['supervisor_code'] !== null) {
                $bossOf[Str::lower($values['code'])] = [Str::lower($values['supervisor_code']), $n];
            }
        }
        foreach ($bossOf as $start => [, $n]) {
            $current = $start;
            $steps = 0;
            while (isset($bossOf[$current]) && $steps++ <= count($bossOf)) {
                $current = $bossOf[$current][0];
                if ($current === $start) {
                    $this->error($n, 'supervisor_code', __('Supervisors form a loop (someone ends up reporting to themselves).'));
                    break;
                }
            }
        }

        // Unique columns: against the database and within the file.
        foreach (['national_id' => __('National ID'), 'tin' => __('TIN'), 'office_email' => __('Office email')] as $column => $label) {
            $taken = Employee::withoutGlobalScope(VisibleToCurrentUser::class)->whereNotNull($column)->pluck($column)
                ->map(fn ($v) => Str::lower((string) $v))->flip()->all();
            $inFile = [];
            foreach ($prepared as $n => $values) {
                if ($values[$column] === null) {
                    continue;
                }
                $key = Str::lower($values[$column]);
                if (isset($taken[$key]) || isset($inFile[$key])) {
                    $this->error($n, $column, __(':label :value is already used.', ['label' => $label, 'value' => $values[$column]]));
                }
                $inFile[$key] = true;
            }
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
     * Creates the employees in one transaction, then links supervisors (they may be new themselves).
     * Each Employee::create also makes the first org-unit history row (Employee::created, 5.4).
     *
     * @param  array<int, array<string, mixed>>  $prepared
     */
    public function import(array $prepared): void
    {
        $connection = (new Employee)->getConnectionName();

        DB::connection($connection)->transaction(function () use ($prepared) {
            $created = [];
            foreach ($prepared as $values) {
                $supervisor = $values['supervisor_code'];
                unset($values['supervisor_code']);
                $created[] = [Employee::create($values), $supervisor];
            }

            $ids = Employee::withoutGlobalScope(VisibleToCurrentUser::class)->pluck('id', 'code')
                ->mapWithKeys(fn ($id, $code) => [Str::lower((string) $code) => $id])->all();
            foreach ($created as [$employee, $supervisor]) {
                if ($supervisor !== null) {
                    $employee->update(['supervisor_id' => $ids[Str::lower($supervisor)]]);
                }
            }
        });
    }

    // ---- helpers ---------------------------------------------------------------------------

    private function error(int $row, string $column, string $message): void
    {
        $this->errors[] = ['row' => $row, 'column' => $column, 'message' => $message];
    }

    private function hasNoError(int $row, string $column): bool
    {
        foreach ($this->errors as $e) {
            if ($e['row'] === $row && $e['column'] === $column) {
                return false;
            }
        }

        return true;
    }

    /**
     * code / name (lower case) => list of ids. A list, so a name used twice can be reported as ambiguous.
     *
     * @param  iterable<Model>  $models
     * @return array<string, list<int>>
     */
    private function lookup(iterable $models): array
    {
        $map = [];
        foreach ($models as $m) {
            foreach (array_filter([$m->getAttribute('code'), $m->getAttribute('name')]) as $key) {
                $map[Str::lower((string) $key)][] = (int) $m->getKey();
            }
        }

        return array_map(fn ($ids) => array_values(array_unique($ids)), $map);
    }

    /** @param  array<string, list<int>>  $lookup */
    private function find(int $row, string $column, ?string $value, array $lookup): ?int
    {
        if ($value === null) {
            return null;
        }
        $ids = $lookup[Str::lower($value)] ?? [];
        if (count($ids) === 1) {
            return $ids[0];
        }
        $this->error($row, $column, $ids === []
            ? __('“:value” was not found.', ['value' => $value])
            : __('“:value” matches more than one. Use the code instead.', ['value' => $value]));

        return null;
    }

    private function gender(int $row, ?string $value): ?int
    {
        if ($value === null) {
            return null;
        }
        foreach (Gender::cases() as $case) {
            if (in_array(Str::lower($value), [Str::lower($case->name), Str::lower($case->label()), (string) $case->value], true)) {
                return $case->value;
            }
        }
        $this->error($row, 'gender', __('Gender must be Male or Female.'));

        return null;
    }

    /** An Excel date (a number like 45321) or text (2024-01-31, 31/01/2024, 31-01-2024). */
    private function date(int $row, string $column, mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
            }
        } catch (Throwable) {
            // not a valid Excel date number: fall through to the error below
        }
        $text = trim((string) $value);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $text);
            } catch (Throwable) {
                continue;   // createFromFormat throws when the text does not fit: try the next format
            }
            // Round trip: rejects things like 31/02/2024 that PHP would quietly turn into 2 March.
            if ($date !== null && $date->format($format) === $text) {
                return $date->toDateString();
            }
        }
        $this->error($row, $column, __('“:value” is not a date. Use YYYY-MM-DD.', ['value' => (string) $value]));

        return null;
    }
}
