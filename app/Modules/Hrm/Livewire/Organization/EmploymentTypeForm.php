<?php

namespace App\Modules\Hrm\Livewire\Organization;

use App\Core\Livewire\LookupForm;
use App\Modules\Hrm\Models\EmploymentType;
use Illuminate\Validation\Rule;

/**
 * New / Edit / Delete employment type (modal "employment-type-form"). [ORG-01]
 * Everything except the fields comes from LookupForm.
 */
class EmploymentTypeForm extends LookupForm
{
    protected function key(): string
    {
        return 'employment-type';
    }

    protected function model(): string
    {
        return EmploymentType::class;
    }

    protected function defaults(): array
    {
        return ['name' => '', 'is_active' => true];
    }

    protected function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique(EmploymentType::class, 'name')->ignore($this->recordId)],
            'is_active' => ['boolean'],
        ];
    }

    protected function view(): string
    {
        return 'hrm::livewire.organization.employment-type-form';
    }
}
