<?php

namespace App\Modules\Hrm\Livewire\Organization;

use App\Core\Livewire\LookupForm;
use App\Modules\Hrm\Models\CostCenter;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Models\OrgUnitType;
use Illuminate\Validation\Rule;

/**
 * New / Edit / Delete org unit (modal "org-unit-form"). [ORG-01]
 *
 * Extra rules (check()) come from OrgUnit::placementProblem(): no loops, the level rule
 * (a Department must be under a Division), no duplicate name under one parent.
 */
class OrgUnitForm extends LookupForm
{
    protected function key(): string
    {
        return 'org-unit';
    }

    protected function model(): string
    {
        return OrgUnit::class;
    }

    protected function defaults(): array
    {
        return ['name' => '', 'code' => '', 'org_unit_type_id' => '', 'parent_id' => '', 'cost_center_id' => '', 'is_active' => true];
    }

    protected function fieldRules(): array
    {
        $parentId = $this->form['parent_id'] ?? null;

        return [
            // Unique among its siblings only: two "Accounts" in different divisions are fine.
            'name' => ['required', 'string', 'max:100', Rule::unique(OrgUnit::class, 'name')
                ->where('parent_id', $parentId === '' ? null : $parentId)->ignore($this->recordId)],
            'code' => ['nullable', 'string', 'max:50', Rule::unique(OrgUnit::class, 'code')->ignore($this->recordId)],
            'org_unit_type_id' => ['required', Rule::exists(OrgUnitType::class, 'id')],
            'parent_id' => ['nullable', Rule::exists(OrgUnit::class, 'id')],
            'cost_center_id' => ['nullable', Rule::exists(CostCenter::class, 'id')],
            'is_active' => ['boolean'],
        ];
    }

    protected function labels(): array
    {
        return ['org_unit_type_id' => __('level'), 'parent_id' => __('parent unit'), 'cost_center_id' => __('cost center')];
    }

    protected function check(array $values): bool
    {
        // The same rules as drag-and-drop in the tree: OrgUnit::placementProblem().
        $unit = $this->recordId ? OrgUnit::findOrFail($this->recordId) : new OrgUnit;
        $unit->name = (string) $values['name'];

        $problem = $unit->placementProblem(
            $values['parent_id'] ? OrgUnit::find($values['parent_id']) : null,
            OrgUnitType::with('parentType')->findOrFail($values['org_unit_type_id']),
        );

        if ($problem !== null) {
            $this->addError('form.parent_id', $problem);

            return false;
        }

        return true;
    }

    protected function viewData(): array
    {
        // Leave the unit itself and its sub-units out of the parent list.
        $except = $this->recordId ? [$this->recordId, ...(OrgUnit::find($this->recordId)?->descendantIds() ?? [])] : [];

        return [
            'types' => OrgUnitType::where('is_active', true)->orderBy('sequence')->pluck('name', 'id')->all(),
            'parents' => OrgUnit::pathOptions($except),
            'costCenters' => CostCenter::where('is_active', true)->orderBy('code')->get()
                ->mapWithKeys(fn (CostCenter $c) => [$c->id => "{$c->code} · {$c->name}"])->all(),
        ];
    }

    protected function view(): string
    {
        return 'hrm::livewire.organization.org-unit-form';
    }
}
