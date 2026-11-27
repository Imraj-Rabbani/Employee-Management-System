<?php

namespace App\Modules\Hrm\Exports;

use App\Modules\Hrm\Enums\Gender;
use App\Modules\Hrm\Imports\EmployeeImporter;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\EmploymentType;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Hrm\Models\Grade;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * The Excel template for importing employees. Two sheets:
 *   "Employees"  the columns of EmployeeImporter::columns() (one "Entity: <type>" column per entity type),
 *                one example row (delete it before importing). Gender, designation, grade, employment type
 *                and every entity column are DROPDOWNS with this company's own values.
 *   "Lists"      the dropdown choices: one column per list (names), with the codes next to them
 * Only the first sheet is imported. The importer accepts a code or a name, so the dropdowns offer names;
 * a name used twice in one list is offered as its code instead (a name alone would match both).
 */
class EmployeeImportTemplate implements Export, WithMultipleSheets
{
    /**
     * @param  array<int, array{name: string, options: array<int, string>, required: bool}>  $choices
     *                                                                                                 per entity type, already limited to what the user may pick
     */
    public function __construct(private array $choices) {}

    /** @return array<int, object> */
    public function sheets(): array
    {
        $columns = EmployeeImporter::columns($this->choices);
        $entityCodes = Entity::query()->pluck('code', 'id');

        // The lists, keyed by the Employees column they feed: [heading, [[value, code], ...]].
        $lists = [
            'gender' => [__('Gender'), array_map(fn (string $label) => [$label, ''], array_values(Gender::options()))],
            'designation' => [__('Designation'), Designation::query()->orderBy('name')->get(['code', 'name'])->map(fn ($d) => [$d->name, $d->code])->all()],
            'grade' => [__('Grade'), Grade::ordered()->get(['code', 'name'])->map(fn ($g) => [$g->name, $g->code])->all()],
            'employment_type' => [__('Employment type'), EmploymentType::query()->orderBy('name')->pluck('name')->map(fn ($n) => [$n, ''])->all()],
        ];
        foreach ($this->choices as $choice) {
            $lists[EmployeeImporter::entityColumn($choice['name'])] = [
                $choice['name'],
                collect($choice['options'])->map(fn ($name, $id) => [$name, $entityCodes[$id] ?? ''])->values()->all(),
            ];
        }
        $lists = array_map(fn (array $list) => [$list[0], self::uniqueValues($list[1])], $lists);

        // Lists sheet: per list a value column and a code column, then an empty column.
        $headings = [];
        $sheetRows = [];
        $dropdowns = [];
        $employeeColumns = array_keys($columns);
        $col = 1;
        foreach ($lists as $key => [$heading, $items]) {
            $headings = [...$headings, $heading, $heading.' '.__('code'), ''];
            foreach ($items as $i => [$value, $code]) {
                $sheetRows[$i] ??= [];
                $sheetRows[$i][$col - 1] = $value;
                $sheetRows[$i][$col] = $code;
            }
            $at = array_search($key, $employeeColumns, true);
            if ($items !== [] && $at !== false) {
                $letter = Coordinate::stringFromColumnIndex($col);
                $dropdowns[Coordinate::stringFromColumnIndex($at + 1)] = "'Lists'!\${$letter}\$2:\${$letter}\$".(count($items) + 1);
            }
            $col += 3;
        }
        $width = count($headings);
        $sheetRows = array_map(fn (array $row) => array_replace(array_fill(0, $width, ''), $row), $sheetRows);

        // Example row: the first choice of every dropdown, made-up values for the rest.
        $example = array_map(fn (string $key) => isset($lists[$key]) ? ($lists[$key][1][0][0] ?? '') : match ($key) {
            'employee_code' => 'E-0101', 'first_name' => 'Rahim', 'last_name' => 'Uddin', 'title' => 'Mr',
            'name_local' => 'রহিম উদ্দিন', 'birth_date' => '1990-05-14', 'join_date' => now()->toDateString(),
            'mobile' => '01711000000', 'office_email' => 'rahim@example.com',
            default => '',
        }, $employeeColumns);

        return [
            new TemplateSheet('Employees', array_column($columns, 0), [$example], $dropdowns, EmployeeImporter::MAX_ROWS),
            new TemplateSheet('Lists', $headings, $sheetRows),
        ];
    }

    /**
     * Each value once: a name that appears twice in the same list is replaced by its code.
     *
     * @param  list<array{0: string, 1: string}>  $items
     * @return list<array{0: string, 1: string}>
     */
    private static function uniqueValues(array $items): array
    {
        $counts = array_count_values(array_map(fn (array $item) => mb_strtolower($item[0]), $items));

        return array_map(
            fn (array $item) => $counts[mb_strtolower($item[0])] > 1 && $item[1] !== '' ? [$item[1], $item[1]] : $item,
            $items,
        );
    }
}
