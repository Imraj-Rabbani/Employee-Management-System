<?php

namespace App\Modules\Hrm\Livewire\Organization;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Core\Livewire\Table\SelectFilter;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Hrm\Models\Position;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/** List of positions. [ORG-01] */
class PositionTable extends DataTable
{
    /** @var array<string, string> refresh the list when its form saved something */
    protected $listeners = ['position-saved' => '$refresh'];

    /** @return Builder<Position> */
    protected function query(): Builder
    {
        return Position::query()->with(['orgUnit', 'grade', 'reportsTo']);
    }

    protected function defaultSort(): array
    {
        return ['name' => 'asc'];
    }

    protected function filters(): array
    {
        return [
            SelectFilter::make('grade_id', __('Grade'), Grade::ordered()->pluck('name', 'id')
                ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])->all()),
            SelectFilter::make('vacancy', __('Vacancy'), ['vacant' => __('Vacant'), 'filled' => __('Filled')])
                ->query(fn (Builder $q, string $value) => $q->where('is_vacant', $value === 'vacant')),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Position'), 'name')->sortable()->searchable()->alwaysVisible()->opens('edit-position'),
            Column::make(__('Code'), 'code')->sortable()->searchable(),
            Column::make(__('Org unit'), 'org_unit_id')->format(fn (Position $p) => $p->orgUnit->name),
            Column::make(__('Grade'), 'grade_id')->format(fn (Position $p) => $p->grade->name ?? '-'),
            Column::make(__('Reports to'), 'parent_id')->format(fn (Position $p) => $p->reportsTo->name ?? '-'),
            Column::make(__('Vacancy'), 'is_vacant')->sortable()
                ->format(fn (Position $p) => new HtmlString(Blade::render(
                    '<x-ui.badge :color="$vacant ? \'warning\' : \'success\'">{{ $vacant ? __(\'Vacant\') : __(\'Filled\') }}</x-ui.badge>',
                    ['vacant' => $p->is_vacant],
                ))),
            Column::active(),
        ];
    }
}
