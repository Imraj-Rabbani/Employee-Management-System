<?php

namespace App\Modules\Hrm\Livewire\Organization;

use App\Core\Livewire\LookupForm;
use App\Modules\Hrm\Models\CostCenter;
use Illuminate\Validation\Rule;

/**
 * New / Edit / Delete cost center (modal "cost-center-form"). [ORG-01]
 * Everything except the fields comes from LookupForm.
 */
class CostCenterForm extends LookupForm
{
    protected function key(): string
    {
        return 'cost-center';
    }

    protected function model(): string
    {
        return CostCenter::class;
    }

    protected function defaults(): array
    {
        return ['code' => '', 'name' => '', 'function_code' => '', 'account_code' => '', 'is_active' => true];
    }

    protected function fieldRules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique(CostCenter::class, 'code')->ignore($this->recordId)],
            'name' => ['required', 'string', 'max:100'],
            'function_code' => ['nullable', 'string', 'max:50'],
            'account_code' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ];
    }

    protected function view(): string
    {
        return 'hrm::livewire.organization.cost-center-form';
    }
}
