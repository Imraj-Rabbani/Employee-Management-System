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
        return ['code' => '', 'name' => '', 'sequence' => 1, 'salary_min' => '', 'salary_max' => '', 'description' => '', 'is_active' => true];
    }

    protected function fieldRules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:50', Rule::unique(Grade::class, 'code')->ignore($this->recordId)],
            'name' => ['required', 'string', 'max:100', Rule::unique(Grade::class, 'name')->ignore($this->recordId)],
            'sequence' => ['required', 'integer', 'min:0', 'max:9999'],
            'salary_min' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'salary_max' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'gte:form.salary_min'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }

    protected function labels(): array
    {
        return ['salary_min' => __('salary from'), 'salary_max' => __('salary to')];
    }

    protected function view(): string
    {
        return 'hrm::livewire.organization.grade-form';
    }
}
