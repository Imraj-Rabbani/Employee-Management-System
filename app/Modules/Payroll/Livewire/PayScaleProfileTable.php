<?php

namespace App\Modules\Payroll\Livewire;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Payroll\Models\PayScaleProfile;
use Illuminate\Database\Eloquent\Builder;

/** List of pay scale profiles. Click a name to edit it. [PC-04] */
class PayScaleProfileTable extends DataTable
{
    /** @var array<string, string> */
    protected $listeners = ['pay-scale-profile-saved' => '$refresh'];

    /** @return Builder<PayScaleProfile> */
    protected function query(): Builder
    {
        // Employees counted company-wide: it says whether the profile is in use.
        return PayScaleProfile::query()
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
            Column::make(__('Name'), 'name')->sortable()->searchable()->alwaysVisible()->opens('edit-pay-scale-profile'),
            Column::make(__('Rules'), 'id')->format(fn (PayScaleProfile $p) => $p->summary()),
            Column::make(__('Employees'), 'employees_count')->sortable()->alignRight(),
            Column::active(),
        ];
    }
}
