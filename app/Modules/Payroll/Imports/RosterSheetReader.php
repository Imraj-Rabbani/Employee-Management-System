<?php

namespace App\Modules\Payroll\Imports;

use App\Modules\Payroll\Actions\SaveRosterCells;
use App\Modules\Payroll\Models\Roster;
use App\Modules\Payroll\Models\Shift;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Reads a roster sheet (the format of RosterExport) into grid cells for SaveRosterCells. (7.2e)
 *
 *   row 1        Code | Name | a date per column (2026-12-01, or a real Excel date)
 *   other rows   employee code | (name, ignored) | per date: shift code, OFF, CALL, or empty (= not planned)
 *
 * Employees not in the file and date columns not in the file stay as they are.
 * All problems are collected (row + column), so the planner can fix the whole file at once.
 */
class RosterSheetReader
{
    public const OFF = 'OFF';

    public const ON_CALL = 'CALL';

    /** @return array{0: array<int, array<string, string>>, 1: list<string>} [cells, problems] */
    public function read(Roster $roster, string $absolutePath): array
    {
        $sheets = Excel::toArray(new class implements Import {}, $absolutePath);

        return $this->parse($roster, $sheets[0] ?? []);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows  the first sheet, row by row
     * @return array{0: array<int, array<string, string>>, 1: list<string>}
     */
    public function parse(Roster $roster, array $rows): array
    {
        $problems = [];
        $header = array_shift($rows) ?? [];
        $inPeriod = array_flip(array_map(fn ($d) => $d->toDateString(), $roster->dates()));

        // Columns 3+ (index 2+) are dates.
        $dateColumns = [];
        foreach (array_slice($header, 2, preserve_keys: true) as $index => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $date = $this->date($value);
            if ($date === null) {
                $problems[] = __('Column :col: ":value" is not a date.', ['col' => $index + 1, 'value' => $value]);
            } elseif (! isset($inPeriod[$date])) {
                $problems[] = __('Column :col: :date is outside the roster period.', ['col' => $index + 1, 'date' => $date]);
            } else {
                $dateColumns[$index] = $date;
            }
        }
        if ($dateColumns === [] && $problems === []) {
            $problems[] = __('No date columns found. Start from "Export to Excel" so the first row has the dates.');
        }

        $employees = $roster->gridEmployees()->keyBy(fn ($e) => mb_strtolower(trim($e->code)));
        $shifts = Shift::query()->where('is_active', true)->get()->keyBy(fn (Shift $s) => mb_strtolower(trim($s->code)));

        $cells = [];
        foreach ($rows as $i => $row) {
            $line = $i + 2;   // + header, 1-based
            $code = trim((string) ($row[0] ?? ''));
            if ($code === '' && array_filter($row, fn ($v) => $v !== null && $v !== '') === []) {
                continue;   // empty line
            }
            $employee = $employees->get(mb_strtolower($code));
            if ($employee === null) {
                $problems[] = __('Row :row: employee ":code" is not in this roster\'s org unit.', ['row' => $line, 'code' => $code]);

                continue;
            }
            if (isset($cells[$employee->id])) {
                $problems[] = __('Row :row: employee :code appears twice.', ['row' => $line, 'code' => $employee->code]);

                continue;
            }

            foreach ($dateColumns as $index => $date) {
                $text = trim((string) ($row[$index] ?? ''));
                $key = mb_strtolower($text);
                $value = match (true) {
                    $text === '' => '',
                    $key === mb_strtolower(self::OFF) => SaveRosterCells::OFF,
                    $key === mb_strtolower(self::ON_CALL) => SaveRosterCells::ON_CALL,
                    $shifts->has($key) => (string) $shifts[$key]->id,
                    default => null,
                };
                if ($value === null) {
                    $problems[] = __('Row :row, :date: ":value" is not a shift code, OFF or CALL.', ['row' => $line, 'date' => $date, 'value' => $text]);
                    $value = '';
                }
                $cells[$employee->id][$date] = $value;
            }
        }

        return [$cells, $problems];
    }

    /** "2026-12-01" as text, or a real Excel date (a number of days since 1900). */
    private function date(mixed $value): ?string
    {
        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
        }
        try {
            $date = Carbon::createFromFormat('Y-m-d', trim((string) $value));
        } catch (\Throwable) {
            return null;
        }

        return $date === null ? null : $date->toDateString();
    }
}
