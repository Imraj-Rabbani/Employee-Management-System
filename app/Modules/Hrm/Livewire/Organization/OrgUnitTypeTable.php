<?php

namespace App\Modules\Hrm\Livewire\Organization;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Modules\Hrm\Models\OrgUnitType;
use Illuminate\Database\Eloquent\Builder;

/** List of unit levels. Click a row's name to edit it. [ORG-01] */
class OrgUnitTypeTable extends DataTable
{
    /** @var array<string, string> refresh the list when its form saved something */
    protected $listeners = ['org-unit-type-saved' => '$refresh'];

    /** @return Builder<OrgUnitType> */
    protected function query(): Builder
    {
        return OrgUnitType::query()->with('parentType')->withCount('units');
    }

    protected function defaultSort(): array
    {
        return ['sequence' => 'asc', 'name' => 'asc'];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Order'), 'sequence')->sortable(),
            Column::make(__('Name'), 'name')->sortable()->searchable()->alwaysVisible()->opens('edit-org-unit-type'),
            Column::make(__('Sits under'), 'parent_type_id')->format(fn (OrgUnitType $t) => $t->parentType->name ?? '-'),
            Column::make(__('Units'), 'units_count')->sortable(),
            Column::active(),
        ];
    }
}
