<?php

namespace App\Modules\Hrm\Livewire\Organization;

use App\Core\Livewire\LookupForm;
use App\Modules\Hrm\Models\OrgUnitType;
use Illuminate\Validation\Rule;

/**
 * New / Edit / Delete unit level (modal "org-unit-type-form"). [ORG-01]
 * Everything except the fields comes from LookupForm.
 */
class OrgUnitTypeForm extends LookupForm
{
    protected function key(): string
    {
        return 'org-unit-type';
    }

    protected function model(): string
    {
        return OrgUnitType::class;
    }

    protected function defaults(): array
    {
        return ['name' => '', 'parent_type_id' => '', 'sequence' => 1, 'is_active' => true];
    }

    protected function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique(OrgUnitType::class, 'name')->ignore($this->recordId)],
            'parent_type_id' => ['nullable', Rule::exists(OrgUnitType::class, 'id'), Rule::notIn([$this->recordId])],
            'sequence' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ];
    }

    protected function labels(): array
    {
        return ['parent_type_id' => __('sits under')];
    }

    protected function viewData(): array
    {
        return [
            'parentTypes' => OrgUnitType::whereKeyNot($this->recordId ?? 0)->orderBy('sequence')->pluck('name', 'id')->all(),
        ];
    }

    protected function view(): string
    {
        return 'hrm::livewire.organization.org-unit-type-form';
    }
}
