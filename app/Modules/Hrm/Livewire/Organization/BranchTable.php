<?php

namespace App\Modules\Hrm\Livewire\Organization;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Modules\Hrm\Models\Branch;
use Illuminate\Database\Eloquent\Builder;

/** List of branches. Click a row's name to edit it. [ORG-01] */
class BranchTable extends DataTable
{
    /** @var array<string, string> refresh the list when its form saved something */
    protected $listeners = ['branch-saved' => '$refresh'];

    /** @return Builder<Branch> */
    protected function query(): Builder
    {
        return Branch::query();
    }

    protected function defaultSort(): array
    {
        return ['name' => 'asc'];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Name'), 'name')->sortable()->searchable()->alwaysVisible()->opens('edit-branch'),
            Column::make(__('Location'), 'location')->searchable(),
            Column::active(),
        ];
    }
}
