<?php

namespace App\Modules\Payroll\Livewire;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Payroll\Models\SalaryProfile;
use Illuminate\Database\Eloquent\Builder;

/** List of salary profiles with their heads: "HR 50% of basic · MED 750.00". Click a name to edit it. [PC-02] */
class SalaryProfileTable extends DataTable
{
    /** @var array<string, string> a renamed head code shows here too */
    protected $listeners = ['salary-profile-saved' => '$refresh', 'salary-head-saved' => '$refresh'];

    /** @return Builder<SalaryProfile> */
    protected function query(): Builder
    {
        // Employees counted company-wide (not only those this user may see): it says whether the profile is in use.
        return SalaryProfile::query()
            ->with(['grade', 'items.head'])
            ->withCount(['employees' => fn (Builder $q) => $q->withoutGlobalScope(VisibleToCurrentUser::class)]);
    }

    protected function defaultSort(): array
    {
        return ['name' => 'asc'];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Code'), 'code')->sortable()->searchable(),
            Column::make(__('Name'), 'name')->sortable()->searchable()->alwaysVisible()->opens('edit-salary-profile'),
            Column::make(__('Grade'), 'grade_id')->format(fn (SalaryProfile $p) => $p->grade->name ?? __('Any')),
            Column::make(__('Heads'), 'id')->format(fn (SalaryProfile $p) => $p->summary()),
            Column::make(__('Employees'), 'employees_count')->sortable()->alignRight(),
            Column::active(),
        ];
    }
}
