<?php

namespace App\Modules\Hrm\Livewire\Employees;

use App\Modules\Hrm\Models\EmployeeEducation;

/**
 * Profile → Education → "Education" card, with a certificate per row. [EMP-03]
 */
class Educations extends EmployeeRecords
{
    protected function relation(): string
    {
        return 'educations';
    }

    protected function attachmentFolder(): string
    {
        return 'employees/educations';
    }

    protected function defaults(): array
    {
        return ['degree' => '', 'institution' => '', 'subject' => '', 'score' => '', 'out_of' => '', 'passing_year' => ''];
    }

    protected function fieldRules(): array
    {
        return [
            'degree' => ['required', 'string', 'max:100'],
            'institution' => ['nullable', 'string', 'max:300'],
            'subject' => ['nullable', 'string', 'max:200'],
            'score' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'out_of' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0'],
            'passing_year' => ['nullable', 'integer', 'between:1950,'.(now()->year + 1)],
        ];
    }

    protected function labels(): array
    {
        return ['out_of' => __('out of'), 'passing_year' => __('passing year')];
    }

    protected function check(array $values): bool
    {
        if ($values['score'] !== null && $values['out_of'] !== null && (float) $values['score'] > (float) $values['out_of']) {
            $this->addError('form.score', __('The score cannot be higher than “out of”.'));

            return false;
        }

        return true;
    }

    protected function viewData(): array
    {
        // Suggestions for the degree box: degrees already typed for anyone in this company.
        return ['degreeSuggestions' => EmployeeEducation::query()->distinct()->orderBy('degree')->limit(200)->pluck('degree')->all()];
    }

    protected function view(): string
    {
        return 'hrm::livewire.employees.educations';
    }
}
