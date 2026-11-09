<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Modules\Hrm\Models\InterviewFactor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** List of interview factors. Click a row's name to edit it. [REC-05] */
class InterviewFactorTable extends DataTable
{
    /** @var array<string, string> refresh the list when its form saved something */
    protected $listeners = ['interview-factor-saved' => '$refresh'];

    /** @return Builder<InterviewFactor> */
    protected function query(): Builder
    {
        return InterviewFactor::query();
    }

    protected function defaultSort(): array
    {
        return ['sequence' => 'asc', 'name' => 'asc'];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Order'), 'sequence')->sortable(),
            Column::make(__('Name'), 'name')->sortable()->searchable()->alwaysVisible()->opens('edit-interview-factor'),
            Column::make(__('Weight'), 'weight')->sortable()->alignRight()
                ->format(fn (Model $row) => rtrim(rtrim((string) $row->getAttribute('weight'), '0'), '.').'%'),
            Column::make(__('Required'), 'is_required')->sortable()
                ->format(fn (Model $row) => $row->getAttribute('is_required') ? __('Yes') : ''),
            Column::make(__('Description'), 'description')->searchable(),
            Column::active(),
        ];
    }
}
