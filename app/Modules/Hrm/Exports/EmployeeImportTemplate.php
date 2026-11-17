<?php

namespace App\Modules\Hrm\Exports;

use App\Modules\Hrm\Imports\EmployeeImporter;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\EmploymentType;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Hrm\Models\Grade;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * The Excel template for importing employees. Two sheets:
 *   "Employees"  the columns of EmployeeImporter::columns() (one "Entity: <type>" column per entity type),
 *                one example row (delete it before importing)
 *   "Lists"      what may be typed in the lookup columns: entities (per type), designations, grades, types
 * Only the first sheet is imported.
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
        $codes = Entity::query()->pluck('code', 'id');

        // Example row: the first allowed entity of each type.
        $firstEntity = [];
        foreach ($this->choices as $choice) {
            $id = array_key_first($choice['options']);
            $firstEntity[EmployeeImporter::entityColumn($choice['name'])] = $id === null ? '' : ($codes[$id] ?? $choice['options'][$id]);
        }
        $example = array_map(fn (string $key) => $firstEntity[$key] ?? match ($key) {
            'employee_code' => 'E-0101', 'first_name' => 'Rahim', 'last_name' => 'Uddin', 'title' => 'Mr',
            'name_local' => 'রহিম উদ্দিন', 'gender' => 'Male', 'birth_date' => '1990-05-14', 'join_date' => now()->toDateString(),
            'designation' => Designation::query()->value('name'), 'grade' => Grade::query()->value('name'),
            'employment_type' => EmploymentType::query()->value('name'), 'mobile' => '01711000000', 'office_email' => 'rahim@example.com',
            default => '',
        }, array_keys($columns));

        // Lists: entities as [type, code, name] rows, next to the other lookups.
        $entities = [];
        foreach ($this->choices as $choice) {
            foreach ($choice['options'] as $id => $name) {
                $entities[] = [$choice['name'], $codes[$id] ?? '', $name];
            }
        }
        $designations = Designation::query()->orderBy('name')->get(['code', 'name'])->all();
        $grades = Grade::ordered()->get(['code', 'name'])->all();
        $types = EmploymentType::query()->orderBy('name')->pluck('name')->all();
        $lists = [];
        $max = max(count($entities), count($designations), count($grades), count($types));
        for ($i = 0; $i < $max; $i++) {
            $lists[] = [
                ...($entities[$i] ?? ['', '', '']), '',
                $designations[$i]->code ?? '', $designations[$i]->name ?? '', '',
                $grades[$i]->code ?? '', $grades[$i]->name ?? '', '',
                $types[$i] ?? '',
            ];
        }

        return [
            new TemplateSheet('Employees', array_column($columns, 0), [$example]),
            new TemplateSheet('Lists', [
                'Entity type', 'Entity code', 'Entity', '',
                'Designation code', 'Designation', '', 'Grade code', 'Grade', '', 'Employment type',
            ], $lists),
        ];
    }
}
