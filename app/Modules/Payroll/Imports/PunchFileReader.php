<?php

namespace App\Modules\Payroll\Imports;

use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Reads an uploaded punch file (CSV or Excel, 7.3c) into punches for StorePunches. [TA-04]
 *
 * First row = column names. Either
 *     access_code | punched_at            e.g.  1001 | 2026-12-01 08:55
 * or  access_code | date | time            e.g.  1001 | 2026-12-01 | 08:55
 * Dates as YYYY-MM-DD (or real Excel dates). Every problem is listed with its row; nothing is saved if any.
 */
class PunchFileReader
{
    public const MAX_ROWS = 20000;

    /** @return array{0: list<array{code: string, punched_at: Carbon}>, 1: list<string>} [punches, problems] */
    public function read(string $absolutePath): array
    {
        // WithHeadingRow: each row is keyed by its column name, "Access Code" → access_code.
        $sheets = Excel::toArray(new class implements Import, WithHeadingRow {}, $absolutePath);

        return $this->parse($sheets[0] ?? []);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows  keyed by column name
     * @return array{0: list<array{code: string, punched_at: Carbon}>, 1: list<string>}
     */
    public function parse(array $rows): array
    {
        $rows = array_values(array_filter($rows, fn (array $r) => array_filter($r, fn ($v) => $v !== null && $v !== '') !== []));
        if ($rows === []) {
            return [[], [__('The file has no punches.')]];
        }
        if (count($rows) > self::MAX_ROWS) {
            return [[], [__('At most :n rows per file. Split the file.', ['n' => self::MAX_ROWS])]];
        }
        $columns = array_keys($rows[0]);
        if (! in_array('access_code', $columns, true) || (! in_array('punched_at', $columns, true) && ! in_array('date', $columns, true))) {
            return [[], [__('The first row must name the columns: access_code and punched_at (or access_code, date and time).')]];
        }

        $punches = [];
        $problems = [];
        foreach ($rows as $i => $row) {
            $line = $i + 2;   // + heading row, 1-based
            $code = trim((string) ($row['access_code'] ?? ''));
            $at = isset($row['punched_at']) ? $this->moment($row['punched_at']) : $this->moment($row['date'] ?? null, $row['time'] ?? null);

            if ($code === '') {
                $problems[] = __('Row :row: the access code is empty.', ['row' => $line]);
            }
            if ($at === null) {
                $problems[] = __('Row :row: the date / time is missing or not like 2026-12-01 08:55.', ['row' => $line]);
            } elseif ($at->isFuture()) {
                $problems[] = __('Row :row: :time is in the future.', ['row' => $line, 'time' => $at->format('Y-m-d H:i')]);
            }
            if ($code !== '' && $at !== null && ! $at->isFuture()) {
                $punches[] = ['code' => $code, 'punched_at' => $at];
            }
        }

        return [$problems === [] ? $punches : [], $problems];
    }

    /** Text "2026-12-01 08:55[:00]", or an Excel date/time number; or a date + a time column. */
    private function moment(mixed $date, mixed $time = null): ?Carbon
    {
        if ($date === null || $date === '') {
            return null;
        }
        if (is_numeric($date)) {
            $moment = Carbon::instance(ExcelDate::excelToDateTimeObject((float) $date));
        } else {
            $moment = $this->parseText(trim((string) $date));
            if ($moment === null) {
                return null;
            }
        }

        if ($time !== null && $time !== '') {
            if (is_numeric($time)) {   // Excel time = fraction of a day
                $seconds = (int) round(fmod((float) $time, 1) * 86400);
                $moment = $moment->copy()->startOfDay()->addSeconds($seconds);
            } elseif (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim((string) $time), $m)) {
                $moment = $moment->copy()->setTime((int) $m[1], (int) $m[2], (int) ($m[3] ?? 0));
            } else {
                return null;
            }
        }

        return $moment;
    }

    private function parseText(string $text): ?Carbon
    {
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $format) {
            // createFromFormat() THROWS when the text does not fit the format; the format() check below
            // also refuses overflowing dates like 2026-02-30 (which PHP would turn into 2 March).
            try {
                $parsed = Carbon::createFromFormat($format, $text);
            } catch (\Throwable) {
                continue;
            }
            if ($parsed !== null && $parsed->format($format) === $text) {
                return $format === 'Y-m-d' ? $parsed->startOfDay() : $parsed;
            }
        }

        return null;
    }
}
