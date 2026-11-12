<?php

namespace App\Modules\Hrm\Livewire\Leave;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Core\Livewire\Table\SelectFilter;
use App\Modules\Hrm\Data\LeaveYear;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveApplication;
use App\Modules\Hrm\Models\LeaveBalance;
use App\Modules\Hrm\Models\LeaveType;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Services\EmployeeVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * HRM → Leave → Balances (8.7): the balance report. One row per employee and leave type of a leave year,
 * straight from the cached leave_balances (= SUM of the ledger), for the employees the user can see.
 * Filter by year (default: this one), type and org unit (incl. units below); export with the table's Export.
 * A name opens the employee's Leave tab, where every number is explained row by row. [LV-03]
 */
class LeaveBalanceTable extends DataTable
{
    public function mount(): void
    {
        $this->filters['year'] ??= (string) LeaveYear::current()->year;
    }

    /** @return Builder<LeaveBalance> */
    protected function query(): Builder
    {
        return LeaveBalance::query()
            ->with(['employee:id,code,first_name,last_name,org_unit_id', 'leaveType:id,code,name,sequence,requires_balance'])
            ->whereIn('employee_id', Employee::query()->select('id'));
    }

    protected function defaultSort(): array
    {
        return ['employee_id' => 'asc'];
    }

    protected function filters(): array
    {
        $current = LeaveYear::current();
        $years = LeaveBalance::query()->distinct()->orderByDesc('year')->pluck('year')->push($current->year)->unique()->sortDesc()
            ->mapWithKeys(fn (int $y) => [$y => LeaveYear::of($y)->label()])->all();

        return [
            SelectFilter::make('year', __('Leave year'), $this->stringKeys($years))->query(fn (Builder $q, string $y) => $q->where('year', (int) $y)),
            SelectFilter::make('type', __('Leave type'), $this->stringKeys(LeaveType::query()->ordered()->pluck('name', 'id')->all()))
                ->query(fn (Builder $q, string $id) => $q->where('leave_type_id', (int) $id)),
            SelectFilter::make('org_unit', __('Org unit'), $this->stringKeys(app(EmployeeVisibility::class)->orgUnitOptionsFor(Auth::user())))
                ->query(fn (Builder $q, string $id) => $q->whereHas('employee', fn (Builder $e) => $e
                    ->whereIn('org_unit_id', [(int) $id, ...(OrgUnit::find((int) $id)?->descendantIds() ?? [])]))),
        ];
    }

    protected function columns(): array
    {
        $num = fn (string $field) => fn (LeaveBalance $b) => LeaveApplication::daysLabel($b->getAttribute($field));

        return [
            Column::make(__('Employee'), 'employee_id')->sortable()->alwaysVisible()
                ->format(fn (LeaveBalance $b) => $b->employee ? $b->employee->code.' · '.$b->employee->name : '—'),
            Column::make(__('Leave type'), 'leave_type_id')->sortable()->format(fn (LeaveBalance $b) => $b->leaveType->name ?? '—'),
            Column::make(__('Year'), 'year')->sortable()->format(fn (LeaveBalance $b) => LeaveYear::of($b->year)->label()),
            Column::make(__('Opening'), 'opening')->alignRight()->format($num('opening')),
            Column::make(__('Earned'), 'earned')->alignRight()->format($num('earned')),
            Column::make(__('Carried'), 'carried_forward')->alignRight()->format($num('carried_forward')),
            Column::make(__('Corrections'), 'adjusted')->alignRight()->format($num('adjusted')),
            Column::make(__('Taken'), 'taken')->sortable()->alignRight()->format($num('taken')),
            Column::make(__('Encashed'), 'encashed')->alignRight()->format($num('encashed')),
            Column::make(__('Expired'), 'expired')->alignRight()->format($num('expired')),
            Column::make(__('Balance'), 'balance')->sortable()->alignRight()
                ->format(fn (LeaveBalance $b) => $b->leaveType?->requires_balance === false ? '—' : LeaveApplication::daysLabel($b->balance)),
        ];
    }
}
