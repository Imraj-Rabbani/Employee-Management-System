<?php

namespace App\Modules\Hrm\Livewire\Organization;

use App\Core\Livewire\LookupForm;
use App\Modules\Hrm\Models\Grade;
use Illuminate\Validation\Rule;

/**
 * New / Edit / Delete grade (modal "grade-form"). [ORG-01]
 * Everything except the fields comes from LookupForm.
 */
class GradeForm extends LookupForm
{
    protected function key(): string
    {
        return 'grade';
    }

    protected function model(): string
    {
        return Grade::class;
    }

    protected function defaults(): array
    {
        return ['code' => '', 'name' => '', 'sequence' => 1, 'description' => '', 'is_active' => true];
    }

    protected function fieldRules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:50', Rule::unique(Grade::class, 'code')->ignore($this->recordId)],
            'name' => ['required', 'string', 'max:100', Rule::unique(Grade::class, 'name')->ignore($this->recordId)],
            'sequence' => ['required', 'integer', 'min:0', 'max:9999'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }

    protected function view(): string
    {
        return 'hrm::livewire.organization.grade-form';
    }
}
