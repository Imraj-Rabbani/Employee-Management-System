<?php

namespace App\Modules\Hrm\Livewire\Organization;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Modules\Hrm\Models\CostCenter;
use Illuminate\Database\Eloquent\Builder;

/** List of cost centers. Click a row's name to edit it. [ORG-01] */
class CostCenterTable extends DataTable
{
    /** @var array<string, string> refresh the list when its form saved something */
    protected $listeners = ['cost-center-saved' => '$refresh'];

    /** @return Builder<CostCenter> */
    protected function query(): Builder
    {
        return CostCenter::query();
    }

    protected function defaultSort(): array
    {
        return ['code' => 'asc'];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Code'), 'code')->sortable()->searchable()->alwaysVisible()->opens('edit-cost-center'),
            Column::make(__('Name'), 'name')->sortable()->searchable(),
            Column::make(__('Function code'), 'function_code')->searchable(),
            Column::make(__('GL account'), 'account_code')->searchable(),
            Column::active(),
        ];
    }
}
