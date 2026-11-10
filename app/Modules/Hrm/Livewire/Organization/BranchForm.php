<?php

namespace App\Modules\Hrm\Livewire\Organization;

use App\Core\Livewire\LookupForm;
use App\Modules\Hrm\Models\Branch;
use Illuminate\Validation\Rule;

/**
 * New / Edit / Delete branch (modal "branch-form"). [ORG-01]
 * Everything except the fields comes from LookupForm.
 */
class BranchForm extends LookupForm
{
    protected function key(): string
    {
        return 'branch';
    }

    protected function model(): string
    {
        return Branch::class;
    }

    protected function defaults(): array
    {
        return ['name' => '', 'location' => '', 'is_active' => true];
    }

    protected function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique(Branch::class, 'name')->ignore($this->recordId)],
            'location' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    protected function view(): string
    {
        return 'hrm::livewire.organization.branch-form';
    }
}
