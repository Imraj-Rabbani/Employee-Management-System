<?php

namespace App\Modules\Hrm\Livewire\Organization;

use App\Core\Livewire\LookupForm;
use App\Modules\Hrm\Models\Designation;
use Illuminate\Validation\Rule;

/**
 * New / Edit / Delete designation (modal "designation-form"). [ORG-01]
 * Everything except the fields comes from LookupForm.
 */
class DesignationForm extends LookupForm
{
    protected function key(): string
    {
        return 'designation';
    }

    protected function model(): string
    {
        return Designation::class;
    }

    protected function defaults(): array
    {
        return ['code' => '', 'name' => '', 'is_active' => true];
    }

    protected function fieldRules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:50', Rule::unique(Designation::class, 'code')->ignore($this->recordId)],
            'name' => ['required', 'string', 'max:100', Rule::unique(Designation::class, 'name')->ignore($this->recordId)],
            'is_active' => ['boolean'],
        ];
    }

    protected function view(): string
    {
        return 'hrm::livewire.organization.designation-form';
    }
}
