<?php

namespace App\Modules\Hrm\Livewire\Organization;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Core\Livewire\Table\SelectFilter;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Models\OrgUnitType;
use Illuminate\Database\Eloquent\Builder;

/** List of org units with their place in the tree. (The tree view itself is task 4.3.) [ORG-01] */
class OrgUnitTable extends DataTable
{
    /** @var array<string, string> refresh the list when its form saved something */
    protected $listeners = ['org-unit-saved' => '$refresh'];

    /** @var array<int, string>|null id => path, built once per render */
    private ?array $paths = null;

    /** @return Builder<OrgUnit> */
    protected function query(): Builder
    {
        return OrgUnit::query()->with(['type', 'costCenter'])->withCount('children');
    }

    protected function defaultSort(): array
    {
        return ['name' => 'asc'];
    }

    protected function filters(): array
    {
        return [
            SelectFilter::make('org_unit_type_id', __('Level'), OrgUnitType::orderBy('sequence')->pluck('name', 'id')
                ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])->all()),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Name'), 'name')->sortable()->searchable()->alwaysVisible()->opens('edit-org-unit'),
            Column::make(__('Under'), 'parent_id')->format(fn (OrgUnit $u) => $u->parent_id ? ($this->paths()[$u->parent_id] ?? '') : '-'),
            Column::make(__('Level'), 'org_unit_type_id')->format(fn (OrgUnit $u) => $u->type->name),
            Column::make(__('Code'), 'code')->sortable()->searchable(),
            Column::make(__('Cost center'), 'cost_center_id')->format(fn (OrgUnit $u) => $u->costCenter->code ?? '-'),
            Column::make(__('Sub-units'), 'children_count')->sortable(),
            Column::active(),
        ];
    }

    /** @return array<int, string> */
    private function paths(): array
    {
        return $this->paths ??= OrgUnit::pathOptions();
    }
}
