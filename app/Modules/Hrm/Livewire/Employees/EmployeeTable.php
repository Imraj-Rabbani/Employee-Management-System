<?php

namespace App\Modules\Hrm\Livewire\Employees;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Core\Livewire\Table\SelectFilter;
use App\Modules\Hrm\Enums\EmployeeStatus;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmploymentType;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Services\EmployeeVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * HRM → Employees: the employee list. [EMP-05]
 *
 * Search, sorting, column chooser ("Columns" menu) and Excel export come from DataTable (1.7).
 * Only the employees the user may see are listed: the VisibleToCurrentUser global scope on Employee
 * filters every query, including the export, so this class does nothing extra for it.
 *
 * The org unit filter includes the units BELOW the chosen one: "Finance" also lists Accounts.
 */
class EmployeeTable extends DataTable
{
    /** @return Builder<Employee> */
    protected function query(): Builder
    {
        // with(): eager loading. 4 extra queries for the whole page instead of 4 per row ("N+1").
        return Employee::query()->with(['designation', 'orgUnit', 'grade', 'employmentType']);
    }

    protected function defaultSort(): array
    {
        return ['code' => 'asc'];
    }

    protected function filters(): array
    {
        return [
            SelectFilter::make('org_unit', __('Org unit'), $this->orgUnitOptions())
                ->query(fn (Builder $q, string $id) => $q->whereIn(
                    'org_unit_id',
                    [(int) $id, ...(OrgUnit::find((int) $id)?->descendantIds() ?? [])],
                )),
            SelectFilter::make('status', __('Status'), $this->stringKeys(EmployeeStatus::options())),
            SelectFilter::make('grade_id', __('Grade'), $this->stringKeys(Grade::ordered()->pluck('name', 'id')->all())),
            SelectFilter::make('employment_type_id', __('Employment type'),
                $this->stringKeys(EmploymentType::orderBy('name')->pluck('name', 'id')->all())),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Code'), 'code')->sortable()->searchable()->alwaysVisible(),
            Column::make(__('Name'), 'first_name')->sortable()->searchable('last_name', 'name_local')->alwaysVisible()
                ->format(fn (Employee $e) => $e->name),
            Column::make(__('Designation'), 'designation_id')->format(fn (Employee $e) => $e->designation->name ?? '-'),
            Column::make(__('Org unit'), 'org_unit_id')->format(fn (Employee $e) => $e->orgUnit->name ?? '-'),
            Column::make(__('Grade'), 'grade_id')->format(fn (Employee $e) => $e->grade->name ?? '-'),
            Column::make(__('Employment type'), 'employment_type_id')->format(fn (Employee $e) => $e->employmentType->name ?? '-'),
            Column::make(__('Joined'), 'join_date')->sortable()->format(fn (Employee $e) => format_date($e->join_date)),
            Column::make(__('Mobile'), 'mobile')->searchable(),
            Column::make(__('Office email'), 'office_email')->searchable(),
            Column::make(__('Status'), 'status')->sortable()
                ->format(fn (Employee $e) => new HtmlString(Blade::render(
                    '<x-ui.badge :color="$status->color()">{{ $status->label() }}</x-ui.badge>',
                    ['status' => $e->status],
                ))),
        ];
    }

    /**
     * Org units as "Head Office › Finance". A user limited to Finance only gets Finance and the units below.
     *
     * @return array<string, string>
     */
    private function orgUnitOptions(): array
    {
        $paths = OrgUnit::pathOptions();
        $user = Auth::user();
        $visible = $user ? app(EmployeeVisibility::class)->orgUnitIdsFor($user) : null;

        if ($visible !== null) {
            $paths = array_intersect_key($paths, array_flip($visible));
        }

        return $this->stringKeys($paths);
    }

    /**
     * Filter values arrive from the browser/URL as strings, so the option keys must be strings too.
     *
     * @param  array<int|string, string>  $options
     * @return array<string, string>
     */
    private function stringKeys(array $options): array
    {
        $out = [];
        foreach ($options as $key => $label) {
            $out[(string) $key] = $label;
        }

        return $out;
    }
}
