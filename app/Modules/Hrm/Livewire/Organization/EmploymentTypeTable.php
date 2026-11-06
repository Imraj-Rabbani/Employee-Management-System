<?php

namespace App\Modules\Hrm\Livewire\Organization;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Modules\Hrm\Models\EmploymentType;
use Illuminate\Database\Eloquent\Builder;

/** List of employment types. Click a row's name to edit it. [ORG-01] */
class EmploymentTypeTable extends DataTable
{
    /** @var array<string, string> refresh the list when its form saved something */
    protected $listeners = ['employment-type-saved' => '$refresh'];

    /** @return Builder<EmploymentType> */
    protected function query(): Builder
    {
        return EmploymentType::query();
    }

    protected function defaultSort(): array
    {
        return ['name' => 'asc'];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Name'), 'name')->sortable()->searchable()->alwaysVisible()->opens('edit-employment-type'),
            Column::active(),
        ];
    }
}
