<?php

namespace App\Modules\Hrm\Livewire\Employees;

use App\Core\Exports\ArrayExport;
use App\Modules\Hrm\Enums\EmployeeStatus;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmploymentType;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Services\EmployeeVisibility;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * HRM → Employees: the employee list.
 *
 * Search, sorting, column chooser ("Columns" menu) and Excel export come from DataTable.
 * Only the employees the user may see are listed: the VisibleToCurrentUser global scope on Employee
 * filters every query, including the export, so this class does nothing extra for it.
 *
 * The org unit filter includes the units BELOW the chosen one: "Finance" also lists Accounts.
 *
 * View: app/Modules/Hrm/resources/views/livewire/employees/employee-table.blade.php
 */
class EmployeeTable extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: '')]
    public string $sortBy = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    /** Filter "Org unit": '' = all */
    #[Url(as: 'org_unit', except: '')]
    public string $orgUnit = '';

    /** Filter "Status": '' = all */
    #[Url(as: 'status', except: '')]
    public string $status = '';

    /** Filter "Grade": '' = all */
    #[Url(as: 'grade_id', except: '')]
    public string $gradeId = '';

    /** Filter "Employment type": '' = all */
    #[Url(as: 'employment_type_id', except: '')]
    public string $employmentTypeId = '';

    #[Url(as: 'per_page', except: 10)]
    public int $perPage = 10;

    /** Columns the user may sort by. Anything else sent from the browser is ignored. */
    private const SORTABLE = ['code', 'first_name', 'join_date', 'status'];

    /**
     * Org units as "Head Office › Finance". A user limited to Finance only gets Finance and the units below.
     *
     * @return array<int, string> org unit id => path
     */
    private function orgUnitOptions(): array
    {
        return app(EmployeeVisibility::class)->orgUnitOptionsFor(Auth::user());
    }

    public function sort(string $field): void
    {
        if (! in_array($field, self::SORTABLE, true)) {
            return;
        }

        // Clicking the same column again flips the direction.
        $this->sortDir = $this->sortBy === $field && $this->sortDir === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $field;
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'orgUnit', 'status', 'gradeId', 'employmentTypeId');
        $this->resetPage();
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (Employee $employee) => [
            $employee->code,
            $employee->name,
            $employee->designation->name ?? '-',
            $employee->orgUnit->name ?? '-',
            $employee->grade->name ?? '-',
            $employee->employmentType->name ?? '-',
            format_date($employee->join_date),
            $employee->mobile,
            $employee->office_email,
            $employee->status->label(),
        ])->all();

        return Excel::download(
            new ArrayExport([__('Code'), __('Name'), __('Designation'), __('Org unit'), __('Grade'), __('Employment type'), __('Joined'), __('Mobile'), __('Office email'), __('Status')], $rows),
            'employee-table-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** Typing a search or picking a filter or page size starts again at page 1. */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'orgUnit', 'status', 'gradeId', 'employmentTypeId', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /** @return Builder<Employee> */
    private function query(): Builder
    {
        // with(): eager loading. 4 extra queries for the whole page instead of 4 per row ("N+1").
        return Employee::query()->with(['designation', 'orgUnit', 'grade', 'employmentType']);
    }

    /** @return array<int|string, string> choices of the "Status" filter */
    private function statusOptions(): array
    {
        return EmployeeStatus::options();
    }

    /** @return array<int|string, string> choices of the "Grade" filter */
    private function gradeIdOptions(): array
    {
        return Grade::ordered()->pluck('name', 'id')->all();
    }

    /** @return array<int|string, string> choices of the "Employment type" filter */
    private function employmentTypeIdOptions(): array
    {
        return EmploymentType::orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return Builder<Employee> */
    private function filteredQuery(): Builder
    {
        $query = $this->query();

        if (trim($this->search) !== '') {
            // addcslashes: a search for "50%" means the text "50%", not "50 followed by anything".
            $like = '%'.addcslashes(trim($this->search), '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('code', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('name_local', 'like', $like)->orWhere('mobile', 'like', $like)->orWhere('office_email', 'like', $like));
        }

        if ($this->orgUnit !== '' && array_key_exists($this->orgUnit, $this->orgUnitOptions())) {
            $query->whereIn(
                'org_unit_id',
                [(int) $this->orgUnit, ...(OrgUnit::find((int) $this->orgUnit)?->descendantIds() ?? [])],
            );
        }

        if ($this->status !== '' && array_key_exists($this->status, $this->statusOptions())) {
            $query->where('status', $this->status);
        }

        if ($this->gradeId !== '' && array_key_exists($this->gradeId, $this->gradeIdOptions())) {
            $query->where('grade_id', $this->gradeId);
        }

        if ($this->employmentTypeId !== '' && array_key_exists($this->employmentTypeId, $this->employmentTypeIdOptions())) {
            $query->where('employment_type_id', $this->employmentTypeId);
        }

        if (in_array($this->sortBy, self::SORTABLE, true)) {
            $query->orderBy($this->sortBy, $this->sortDir === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('code', 'asc');
        }

        return $query;
    }

    public function render(): View
    {
        // A page size typed into the URL that is not offered falls back to 10.
        $perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 10;

        return view('hrm::livewire.employees.employee-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
            'orgUnitOptions' => $this->orgUnitOptions(),
            'statusOptions' => $this->statusOptions(),
            'gradeIdOptions' => $this->gradeIdOptions(),
            'employmentTypeIdOptions' => $this->employmentTypeIdOptions(),
        ]);
    }
}
