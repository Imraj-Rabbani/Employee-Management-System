<?php

namespace App\Modules\Hrm\Livewire\Employees;

use App\Modules\Hrm\Enums\BloodGroup;
use App\Modules\Hrm\Enums\Gender;
use App\Modules\Hrm\Enums\MaritalStatus;
use App\Modules\Hrm\Enums\Religion;
use App\Modules\Hrm\Models\Employee;
use Illuminate\Validation\Rule;

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
        return [
            'title' => ['nullable', 'string', 'max:20'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'name_local' => ['nullable', 'string', 'max:200'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'marital_status' => ['nullable', Rule::enum(MaritalStatus::class)],
            'blood_group' => ['nullable', Rule::enum(BloodGroup::class)],
            'religion' => ['nullable', Rule::enum(Religion::class)],
            'nationality' => ['nullable', 'string', 'max:50'],
            // unique ... ignore(): no OTHER employee may have the same number (this one may keep its own).
            'national_id' => ['nullable', 'string', 'max:30', Rule::unique(Employee::class, 'national_id')->ignore($this->employeeId)],
            'tin' => ['nullable', 'string', 'max:30', Rule::unique(Employee::class, 'tin')->ignore($this->employeeId)],
            'passport_no' => ['nullable', 'string', 'max:30'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'office_email' => ['nullable', 'email', 'max:255', Rule::unique(Employee::class, 'office_email')->ignore($this->employeeId)],
        ];
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
