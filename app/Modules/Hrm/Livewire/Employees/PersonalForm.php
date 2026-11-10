<?php

namespace App\Modules\Hrm\Livewire\Employees;

use App\Modules\Hrm\Enums\BloodGroup;
use App\Modules\Hrm\Enums\Gender;
use App\Modules\Hrm\Enums\MaritalStatus;
use App\Modules\Hrm\Enums\Religion;
use App\Modules\Hrm\Models\Employee;

/**
 * Profile → Personal → Edit: name, personal details, identity and contact. [EMP-01]
 */
class PersonalForm extends EmployeeSectionForm
{
    protected function section(): string
    {
        return 'personal';
    }

    protected function fieldRules(): array
    {
        return Employee::personalRules($this->employeeId);   // shared with profile update requests (5.8)
    }

    protected function labels(): array
    {
        return [
            'first_name' => __('first name'), 'last_name' => __('last name'), 'name_local' => __('local name'),
            'birth_date' => __('date of birth'), 'marital_status' => __('marital status'), 'blood_group' => __('blood group'),
            'national_id' => __('national ID'), 'tin' => __('TIN'), 'passport_no' => __('passport no.'),
            'personal_email' => __('personal email'), 'office_email' => __('office email'),
        ];
    }

    protected function viewData(): array
    {
        return [
            'genders' => Gender::options(),
            'maritalStatuses' => MaritalStatus::options(),
            'bloodGroups' => BloodGroup::options(),
            'religions' => Religion::options(),
        ];
    }

    protected function view(): string
    {
        return 'hrm::livewire.employees.personal-form';
    }
}
