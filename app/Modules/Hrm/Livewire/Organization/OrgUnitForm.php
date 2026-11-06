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
 * Extra rules (check()):
 *   - not under itself or one of its own sub-units (a loop)
 *   - if the unit's level "sits under" another level, the parent must be of that level
 *     (e.g. a Department must be under a Division)
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
        $parent = $values['parent_id'] ? OrgUnit::with('type')->find($values['parent_id']) : null;

        if ($this->recordId && $parent
            && ($parent->id === $this->recordId || in_array($parent->id, OrgUnit::findOrFail($this->recordId)->descendantIds(), true))) {
            $this->addError('form.parent_id', __('A unit cannot be placed under itself or one of its own sub-units.'));

            return false;
        }

        $type = OrgUnitType::with('parentType')->findOrFail($values['org_unit_type_id']);
        if ($type->parentType && $parent?->org_unit_type_id !== $type->parent_type_id) {
            $this->addError('form.parent_id', __('A :level must be placed under a :parent.', [
                'level' => $type->name, 'parent' => $type->parentType->name,
            ]));

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
