<?php

namespace App\Core\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Turns plain rows into an Excel file. Used by the "Export" button of list pages:
 *
 *     return Excel::download(new ArrayExport(['Name', 'Email'], $rows), 'users.xlsx');
 *
 * $rows is a list of lists, one inner list per Excel row, in the same order as the headings.
 */
class ArrayExport implements FromArray, ShouldAutoSize, WithHeadings
{
    /**
     * @param  list<string>  $headings
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function __construct(
        private array $headings,
        private array $rows,
    ) {}

    /** @return list<string> */
    public function headings(): array
    {
        return $this->headings;
    }

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        return $this->rows;
    }
}
