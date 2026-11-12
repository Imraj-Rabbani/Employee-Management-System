<?php

namespace App\Modules\Hrm\Livewire\Leave;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Modules\Hrm\Models\LeaveProfile;
use App\Modules\Hrm\Models\LeaveProfileItem;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use Illuminate\Database\Eloquent\Builder;

/** List of leave profiles with what they give: "CL 10 · SL 14". Click a name to edit it. [LV-02] */
class LeaveProfileTable extends DataTable
{
    /** @var array<string, string> a renamed leave type code shows here too */
    protected $listeners = ['leave-profile-saved' => '$refresh', 'leave-type-saved' => '$refresh'];

    /** @return Builder<LeaveProfile> */
    protected function query(): Builder
    {
        // Employees counted company-wide (not only those this user may see): it says whether the profile is in use.
        return LeaveProfile::query()
            ->with('items.leaveType')
            ->withCount(['employees' => fn (Builder $q) => $q->withoutGlobalScope(VisibleToCurrentUser::class)]);
    }

    protected function defaultSort(): array
    {
        return ['name' => 'asc'];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Name'), 'name')->sortable()->searchable()->alwaysVisible()->opens('edit-leave-profile'),
            Column::make(__('Days per year'), 'id')->format(fn (LeaveProfile $p) => $p->items
                ->sortBy(fn (LeaveProfileItem $i) => [$i->leaveType->sequence, $i->leaveType->name])
                ->map(fn (LeaveProfileItem $i) => $i->leaveType->code.' '.$i->days_per_year)
                ->implode(' · ') ?: '—'),
            Column::make(__('Employees'), 'employees_count')->sortable()->alignRight(),
            Column::make(__('Detail'), 'detail')->searchable(),
            Column::active(),
        ];
    }
}
