<?php

namespace App\Core\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Excel export of rows worked out in PHP (reports that are not one database query, e.g. the recruitment
 * funnel). For a plain list use a DataTable: it exports itself (TableExport).
 *
 *     Excel::download(new ArrayExport(['Posting', 'Applied'], [['JOB-0001', 12], ...]), 'funnel.xlsx');
 */
class ArrayExport implements FromArray, ShouldAutoSize, WithHeadings
{
    /**
     * @param  list<string>  $headings
     * @param  list<list<string|int|float|null>>  $rows
     */
    public function __construct(private array $headings, private array $rows) {}

    /** @return list<list<string|int|float|null>> */
    public function array(): array
    {
        return $this->rows;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return $this->headings;
    }
}
