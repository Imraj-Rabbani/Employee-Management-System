<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Core\Livewire\Table\SelectFilter;
use App\Modules\Hrm\Services\EmployeeVisibility;
use App\Modules\Payroll\Enums\PunchSource;
use App\Modules\Payroll\Models\PunchLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Payroll → Attendance → Punches: every punch, newest first. [TA-04]
 * Punches of employees the user can see; unmatched punches (no employee has that code) only for users
 * without an org-unit limit, since nobody can tell whose they are.
 */
class PunchTable extends DataTable
{
    /** @var array<string, string> */
    protected $listeners = ['punches-saved' => '$refresh'];

    /** @return Builder<PunchLog> */
    protected function query(): Builder
    {
        $seesAll = app(EmployeeVisibility::class)->orgUnitIdsFor(Auth::user()) === null;

        // whereHas('employee') goes through Employee's visibility scope: only employees this user may see.
        return PunchLog::query()
            ->with(['employee:id,code,first_name,last_name', 'device:id,name'])
            ->where(fn (Builder $q) => $q->whereHas('employee')->when($seesAll, fn (Builder $q) => $q->orWhereNull('employee_id')));
    }

    protected function defaultSort(): array
    {
        return ['punched_at' => 'desc'];
    }

    protected function filters(): array
    {
        return [
            SelectFilter::make('source', __('Source'), $this->stringKeys(PunchSource::options())),
            SelectFilter::make('matched', __('Employee'), ['yes' => __('Matched'), 'no' => __('Unmatched')])
                ->query(fn (Builder $q, string $v) => $v === 'yes' ? $q->whereNotNull('employee_id') : $q->whereNull('employee_id')),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Time'), 'punched_at')->sortable()->alwaysVisible()->format(fn (PunchLog $p) => format_datetime($p->punched_at)),
            Column::make(__('Employee'), 'employee_id')->alwaysVisible()
                ->format(fn (PunchLog $p) => $p->employee ? $p->employee->code.' · '.$p->employee->name : __('Unmatched')),
            Column::make(__('Attendance ID'), 'access_code')->sortable()->searchable(),
            Column::make(__('Source'), 'source')->sortable()->format(fn (PunchLog $p) => $p->source->label()),
            Column::make(__('Device'), 'device_id')->format(fn (PunchLog $p) => $p->device->name ?? '—'),
            Column::make(__('Distance'), 'distance_m')->alignRight()
                ->format(fn (PunchLog $p) => $p->distance_m === null ? '—' : __(':m m', ['m' => number_format($p->distance_m)])),
        ];
    }
}
