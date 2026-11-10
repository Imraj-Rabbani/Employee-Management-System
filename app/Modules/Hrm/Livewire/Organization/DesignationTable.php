<?php

namespace App\Modules\Hrm\Livewire\Organization;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Modules\Hrm\Models\Designation;
use Illuminate\Database\Eloquent\Builder;

/** List of designations. Click a row's name to edit it. [ORG-01] */
class DesignationTable extends DataTable
{
    /** @var array<string, string> refresh the list when its form saved something */
    protected $listeners = ['designation-saved' => '$refresh'];

    /** @return Builder<Designation> */
    protected function query(): Builder
    {
        return Designation::query();
    }

    protected function defaultSort(): array
    {
        return ['name' => 'asc'];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Name'), 'name')->sortable()->searchable()->alwaysVisible()->opens('edit-designation'),
            Column::make(__('Code'), 'code')->sortable()->searchable(),
            Column::active(),
        ];
    }
}
