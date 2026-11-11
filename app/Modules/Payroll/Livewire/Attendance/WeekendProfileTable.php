<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Payroll\Models\WeekendProfile;
use Illuminate\Database\Eloquent\Builder;

/** List of weekend profiles. Click a row's name to edit it. [TA-02] */
class WeekendProfileTable extends DataTable
{
    /** @var array<string, string> */
    protected $listeners = ['weekend-profile-saved' => '$refresh'];

    /** @return Builder<WeekendProfile> */
    protected function query(): Builder
    {
        return WeekendProfile::query()
            ->withCount(['employees' => fn (Builder $q) => $q->withoutGlobalScope(VisibleToCurrentUser::class)]);
    }

    protected function defaultSort(): array
    {
        return ['name' => 'asc'];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Name'), 'name')->sortable()->searchable()->alwaysVisible()->opens('edit-weekend-profile'),
            Column::make(__('Code'), 'code')->sortable()->searchable(),
            Column::make(__('Days off'), 'day_one')->format(fn (WeekendProfile $p) => $p->summary()),
            Column::make(__('Home office'), 'home_office_day')->format(fn (WeekendProfile $p) => $p->home_office_day?->label() ?? '—'),
            Column::make(__('Employees'), 'employees_count')->sortable()->alignRight(),
            Column::active(),
        ];
    }
}
