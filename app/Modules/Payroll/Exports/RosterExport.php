<?php

namespace App\Modules\Payroll\Exports;

use App\Modules\Hrm\Exports\TemplateSheet;
use App\Modules\Payroll\Imports\RosterSheetReader;
use App\Modules\Payroll\Models\Roster;
use App\Modules\Payroll\Models\Shift;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * A roster as Excel (7.2e). Two sheets:
 *   "Roster"  Code | Name | 2026-12-01 | 2026-12-02 | …   one row per employee, cells = shift code, OFF, CALL or empty
 *   "Codes"   what may be typed in the cells
 * The same file, changed and uploaded again, is the import format (RosterSheetReader). Only "Roster" is read.
 */
class RosterExport implements Export, WithMultipleSheets
{
    public function __construct(private Roster $roster) {}

    /** @return array<int, object> */
    public function sheets(): array
    {
        $employees = $this->roster->gridEmployees();
        $matrix = $this->roster->cellMatrix($employees);
        $codes = Shift::query()->pluck('code', 'id');

        $rows = [];
        foreach ($employees as $employee) {
            $row = [$employee->code, $employee->name];
            foreach ($matrix[$employee->id] as $value) {
                $row[] = match ($value) {
                    'off' => RosterSheetReader::OFF,
                    'call' => RosterSheetReader::ON_CALL,
                    '' => '',
                    default => $codes[(int) $value] ?? '',
                };
            }
            $rows[] = $row;
        }

        $dates = array_map(fn ($d) => $d->toDateString(), $this->roster->dates());
        $legend = Shift::query()->where('is_active', true)->orderBy('code')->get()
            ->map(fn (Shift $s) => [$s->code, $s->name, $s->timesLabel()])->all();
        $legend[] = [RosterSheetReader::OFF, __('Day off'), ''];
        $legend[] = [RosterSheetReader::ON_CALL, __('On call'), ''];
        $legend[] = ['', __('Empty = not planned (own / default shift applies)'), ''];

        return [
            new TemplateSheet('Roster', ['Code', 'Name', ...$dates], $rows),
            new TemplateSheet('Codes', [__('Type in a cell'), __('Meaning'), __('Time')], $legend),
        ];
    }

    public function fileName(): string
    {
        return $this->roster->number().'.xlsx';
    }
}
