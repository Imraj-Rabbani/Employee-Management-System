<?php

namespace App\Modules\Hrm\Livewire\Employees;

use App\Modules\Hrm\Enums\EmployeeRelation;
use App\Modules\Hrm\Enums\Gender;
use Illuminate\Validation\Rule;

/**
 * Profile → Family & Nominees → "Family members" card. [EMP-02]
 */
class FamilyMembers extends EmployeeRecords
{
    protected function relation(): string
    {
        return 'familyMembers';
    }

    protected function defaults(): array
    {
        return ['relation' => '', 'name' => '', 'name_local' => '', 'birth_date' => '', 'gender' => '', 'occupation' => '', 'phone' => ''];
    }

    protected function fieldRules(): array
    {
        return [
            'relation' => ['required', Rule::enum(EmployeeRelation::class)],
            'name' => ['required', 'string', 'max:200'],
            'name_local' => ['nullable', 'string', 'max:200'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'occupation' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    protected function labels(): array
    {
        return ['name_local' => __('local name'), 'birth_date' => __('date of birth')];
    }

    protected function viewData(): array
    {
        return ['relations' => EmployeeRelation::options(), 'genders' => Gender::options()];
    }

    protected function view(): string
    {
        return 'hrm::livewire.employees.family-members';
    }
}
