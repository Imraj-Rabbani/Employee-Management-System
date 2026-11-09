<?php

namespace App\Modules\Hrm\Livewire\Organization;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Modules\Hrm\Models\Grade;
use Illuminate\Database\Eloquent\Builder;

/** List of grades. Click a row's name to edit it. [ORG-01] */
class GradeTable extends DataTable
{
    /** @var array<string, string> refresh the list when its form saved something */
    protected $listeners = ['grade-saved' => '$refresh'];

    /** @return Builder<Grade> */
    protected function query(): Builder
    {
        return Grade::query();
    }

    protected function defaultSort(): array
    {
        return ['sequence' => 'asc', 'name' => 'asc'];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Order'), 'sequence')->sortable(),
            Column::make(__('Name'), 'name')->sortable()->searchable()->alwaysVisible()->opens('edit-grade'),
            Column::make(__('Code'), 'code')->sortable()->searchable(),
            Column::make(__('Salary band'), 'salary_min')->sortable()->alignRight()->format(fn (Grade $g) => $g->salaryRange() ?? '—'),
            Column::make(__('Description'), 'description')->searchable(),
            Column::active(),
        ];
    }
}
