<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Payroll\Models\HolidayProfile;
use Illuminate\Database\Eloquent\Builder;

/** List of holiday calendars. Click a name to edit it. [TA-02] */
class HolidayProfileTable extends DataTable
{
    /** @var array<string, string> also refreshed when a holiday was saved: the holiday counts change */
    protected $listeners = ['holiday-profile-saved' => '$refresh', 'holiday-saved' => '$refresh'];

    /** @return Builder<HolidayProfile> */
    protected function query(): Builder
    {
        return HolidayProfile::query()
            ->withCount(['holidays', 'employees' => fn (Builder $q) => $q->withoutGlobalScope(VisibleToCurrentUser::class)]);
    }

    protected function defaultSort(): array
    {
        return ['name' => 'asc'];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Name'), 'name')->sortable()->searchable()->alwaysVisible()->opens('edit-holiday-profile'),
            Column::make(__('Holidays'), 'holidays_count')->sortable()->alignRight(),
            Column::make(__('Employees'), 'employees_count')->sortable()->alignRight(),
            Column::make(__('Detail'), 'detail')->searchable(),
            Column::active(),
        ];
    }
}
