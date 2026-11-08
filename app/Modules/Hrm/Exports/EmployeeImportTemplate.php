<?php

namespace App\Modules\Hrm\Exports;

use App\Modules\Hrm\Imports\EmployeeImporter;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\EmploymentType;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Hrm\Models\OrgUnit;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * The Excel template for importing employees (5.6). Two sheets:
 *   "Employees"  the columns of EmployeeImporter::COLUMNS, one example row (delete it before importing)
 *   "Lists"      what may be typed in the lookup columns: org units, designations, grades, types
 * Only the first sheet is imported.
 */
class EmployeeImportTemplate implements Export, WithMultipleSheets
{
    /** @param  array<int, string>  $orgUnits  id => path, already limited to what the user may see */
    public function __construct(private array $orgUnits) {}

    /** @return array<int, object> */
    public function sheets(): array
    {
        $headings = array_column(EmployeeImporter::COLUMNS, 0);
        $example = [
            'E-0101', 'Rahim', 'Uddin', 'Mr', 'রহিম উদ্দিন', 'Male', '1990-05-14', now()->toDateString(),
            $this->firstUnitCode(), Designation::query()->value('name'), Grade::query()->value('name'),
            EmploymentType::query()->value('name'), '', '01711000000', '', 'rahim@example.com', '', '',
        ];

        $codes = OrgUnit::query()->whereIn('id', array_keys($this->orgUnits))->pluck('code', 'id');
        $lists = [];
        $units = array_map(null, array_keys($this->orgUnits), array_values($this->orgUnits));
        $designations = Designation::query()->orderBy('name')->get(['code', 'name'])->all();
        $grades = Grade::ordered()->get(['code', 'name'])->all();
        $types = EmploymentType::query()->orderBy('name')->pluck('name')->all();
        $max = max(count($units), count($designations), count($grades), count($types));
        for ($i = 0; $i < $max; $i++) {
            $lists[] = [
                isset($units[$i]) ? ($codes[$units[$i][0]] ?? '') : '', $units[$i][1] ?? '', '',
                $designations[$i]->code ?? '', $designations[$i]->name ?? '', '',
                $grades[$i]->code ?? '', $grades[$i]->name ?? '', '',
                $types[$i] ?? '',
            ];
        }

        return [
            new TemplateSheet('Employees', $headings, [$example]),
            new TemplateSheet('Lists', [
                'Org unit code', 'Org unit (type the code, or the last name in the path)', '',
                'Designation code', 'Designation', '', 'Grade code', 'Grade', '', 'Employment type',
            ], $lists),
        ];
    }

    private function firstUnitCode(): string
    {
        $unit = OrgUnit::query()->whereIn('id', array_keys($this->orgUnits))->orderBy('id')->first();

        return (string) ($unit->code ?? $unit->name ?? '');
    }
}
