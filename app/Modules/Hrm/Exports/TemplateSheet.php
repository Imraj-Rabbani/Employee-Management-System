<?php

namespace App\Modules\Hrm\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

/**
 * One sheet of a template.
 *
 * $dropdowns turns columns into Excel dropdowns: [column letter => cell range with the choices], e.g.
 *     ['H' => "'Lists'!\$C\$2:\$C\$12"]   column H (from row 2 down to $dropdownRows) offers Lists!C2:C12
 * Picking from the list is enforced while typing (pasted values are not checked by Excel; the importer checks them).
 */
class TemplateSheet implements FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithTitle
{
    /**
     * @param  list<string>  $headings
     * @param  list<list<mixed>>  $rows
     * @param  array<string, string>  $dropdowns
     */
    public function __construct(
        private string $title,
        private array $headings,
        private array $rows,
        private array $dropdowns = [],
        private int $dropdownRows = 1000,
    ) {}

    /** @return list<list<mixed>> */
    public function array(): array
    {
        return $this->rows;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return $this->title;
    }

    /** @return array<class-string, callable> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = $this->dropdownRows + 1;   // row 1 = headings

                foreach ($this->dropdowns as $column => $range) {
                    $validation = (new DataValidation)
                        ->setType(DataValidation::TYPE_LIST)
                        ->setErrorStyle(DataValidation::STYLE_STOP)
                        ->setAllowBlank(true)
                        ->setShowDropDown(true)        // (PhpSpreadsheet's name for "show the arrow")
                        ->setShowErrorMessage(true)
                        ->setErrorTitle(__('Not in the list'))
                        ->setError(__('Pick a value from the dropdown. The choices are on the Lists sheet.'))
                        ->setFormula1($range);

                    $sheet->setDataValidation("{$column}2:{$column}{$last}", $validation);
                }
            },
        ];
    }
}
