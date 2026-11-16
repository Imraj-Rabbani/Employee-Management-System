<?php

namespace App\Core\Support;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * A date cell from an uploaded sheet: a real Excel date (a number like 45321) or text (2024-01-31, 31/01/2024,
 * 31-01-2024, 31.01.2024). Same rules as the employee import.
 *
 *     SpreadsheetDate::parse($cell)   '2024-01-31', null for an empty cell, InvalidArgumentException otherwise
 */
final class SpreadsheetDate
{
    public static function parse(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
            } catch (Throwable) {
                // not a valid Excel date number
            }
        }
        $text = trim((string) $value);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $text);
            } catch (Throwable) {
                continue;
            }
            // Round trip: rejects 31/02/2024, which PHP would quietly turn into 2 March.
            if ($date !== null && $date->format($format) === $text) {
                return $date->toDateString();
            }
        }

        throw new InvalidArgumentException(__('“:value” is not a date. Use YYYY-MM-DD.', ['value' => $text]));
    }
}
