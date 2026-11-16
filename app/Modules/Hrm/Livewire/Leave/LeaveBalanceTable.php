<?php

namespace App\Modules\Hrm\Livewire\Leave;

use App\Core\Exports\ArrayExport;
use App\Modules\Hrm\Data\LeaveYear;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveApplication;
use App\Modules\Hrm\Models\LeaveBalance;
use App\Modules\Hrm\Models\LeaveType;
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
 * HRM → Leave → Balances: the balance report. One row per employee and leave type of a leave year,
 * straight from the cached leave_balances (= SUM of the ledger), for the employees the user can see.
 * Filter by year (default: this one), type and org unit (incl. units below); export with the table's Export.
 * A name opens the employee's Leave tab, where every number is explained row by row.
 *
 * View: app/Modules/Hrm/resources/views/livewire/leave/leave-balance-table.blade.php
 */
class LeaveBalanceTable extends Component
{
    use WithPagination;

    #[Url(as: 'sort', except: '')]
    public string $sortBy = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    /** Filter "Leave year": '' = all */
    #[Url(as: 'year', except: '')]
    public string $year = '';

    /** Filter "Leave type": '' = all */
    #[Url(as: 'type', except: '')]
    public string $type = '';

    /** Filter "Org unit": '' = all */
    #[Url(as: 'org_unit', except: '')]
    public string $orgUnit = '';

    #[Url(as: 'per_page', except: 10)]
    public int $perPage = 10;

    /** Columns the user may sort by. Anything else sent from the browser is ignored. */
    private const SORTABLE = ['employee_id', 'leave_type_id', 'year', 'taken', 'balance'];

    public function mount(): void
    {
        $this->year = $this->year ?: (string) LeaveYear::current()->year;
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
        $this->reset('year', 'type', 'orgUnit');
        $this->resetPage();
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (LeaveBalance $leaveBalance) => [
            $leaveBalance->employee ? $leaveBalance->employee->code.' · '.$leaveBalance->employee->name : '—',
            $leaveBalance->leaveType->name ?? '—',
            LeaveYear::of($leaveBalance->year)->label(),
            LeaveApplication::daysLabel($leaveBalance->getAttribute('opening')),
            LeaveApplication::daysLabel($leaveBalance->getAttribute('earned')),
            LeaveApplication::daysLabel($leaveBalance->getAttribute('carried_forward')),
            LeaveApplication::daysLabel($leaveBalance->getAttribute('adjusted')),
            LeaveApplication::daysLabel($leaveBalance->getAttribute('taken')),
            LeaveApplication::daysLabel($leaveBalance->getAttribute('encashed')),
            LeaveApplication::daysLabel($leaveBalance->getAttribute('expired')),
            $leaveBalance->leaveType?->requires_balance === false ? '—' : LeaveApplication::daysLabel($leaveBalance->balance),
        ])->all();

        return Excel::download(
            new ArrayExport([__('Employee'), __('Leave type'), __('Year'), __('Opening'), __('Earned'), __('Carried'), __('Corrections'), __('Taken'), __('Encashed'), __('Expired'), __('Balance')], $rows),
            'leave-balance-table-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** Typing a search or picking a filter or page size starts again at page 1. */
    public function updated(string $property): void
    {
        if (in_array($property, ['year', 'type', 'orgUnit', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /** @return Builder<LeaveBalance> */
    private function query(): Builder
    {
        return LeaveBalance::query()
            ->with(['employee:id,code,first_name,last_name,org_unit_id', 'leaveType:id,code,name,sequence,requires_balance'])
            ->whereIn('employee_id', Employee::query()->select('id'));
    }

    /** @return array<int|string, string> choices of the "Leave year" filter */
    private function yearOptions(): array
    {
        $current = LeaveYear::current();

        $years = LeaveBalance::query()->distinct()->orderByDesc('year')->pluck('year')->push($current->year)->unique()->sortDesc()
            ->mapWithKeys(fn (int $y) => [$y => LeaveYear::of($y)->label()])->all();

        return $years;
    }

    /** @return array<int|string, string> choices of the "Leave type" filter */
    private function typeOptions(): array
    {
        return LeaveType::query()->ordered()->pluck('name', 'id')->all();
    }

    /** @return array<int|string, string> choices of the "Org unit" filter */
    private function orgUnitOptions(): array
    {
        return app(EmployeeVisibility::class)->orgUnitOptionsFor(Auth::user());
    }

    /** @return Builder<LeaveBalance> */
    private function filteredQuery(): Builder
    {
        $query = $this->query();

        if ($this->year !== '' && array_key_exists($this->year, $this->yearOptions())) {
            $query->where('year', (int) $this->year);
        }

        if ($this->type !== '' && array_key_exists($this->type, $this->typeOptions())) {
            $query->where('leave_type_id', (int) $this->type);
        }

        if ($this->orgUnit !== '' && array_key_exists($this->orgUnit, $this->orgUnitOptions())) {
            $query->whereHas('employee', fn (Builder $e) => $e
                ->whereIn('org_unit_id', [(int) $this->orgUnit, ...(OrgUnit::find((int) $this->orgUnit)?->descendantIds() ?? [])]));
        }

        if (in_array($this->sortBy, self::SORTABLE, true)) {
            $query->orderBy($this->sortBy, $this->sortDir === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('employee_id', 'asc');
        }

        return $query;
    }

    public function render(): View
    {
        // A page size typed into the URL that is not offered falls back to 10.
        $perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 10;

        return view('hrm::livewire.leave.leave-balance-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
            'yearOptions' => $this->yearOptions(),
            'typeOptions' => $this->typeOptions(),
            'orgUnitOptions' => $this->orgUnitOptions(),
        ]);
    }
}
