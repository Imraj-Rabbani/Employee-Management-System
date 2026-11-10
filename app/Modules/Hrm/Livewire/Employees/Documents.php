<?php

namespace App\Modules\Hrm\Livewire\Employees;

use App\Modules\Hrm\Enums\DocumentType;
use Illuminate\Validation\Rule;

/**
 * Profile → Documents: NID, passport, licences ... with expiry warnings and a scan per row. [EMP-03]
 */
class Documents extends EmployeeRecords
{
    protected function relation(): string
    {
        return 'documents';
    }

    protected function attachmentFolder(): string
    {
        return 'employees/documents';
    }

    protected function defaults(): array
    {
        return ['type' => '', 'title' => '', 'number' => '', 'issue_date' => '', 'expiry_date' => ''];
    }

    protected function fieldRules(): array
    {
        return [
            'type' => ['required', Rule::enum(DocumentType::class)],
            'title' => ['nullable', 'required_if:form.type,'.DocumentType::Other->value, 'string', 'max:150'],
            'number' => ['nullable', 'string', 'max:100'],
            'issue_date' => ['nullable', 'date', 'before_or_equal:today'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:form.issue_date'],
        ];
    }

    protected function labels(): array
    {
        return ['issue_date' => __('issue date'), 'expiry_date' => __('expiry date')];
    }

    protected function viewData(): array
    {
        return ['types' => DocumentType::options(), 'other' => DocumentType::Other->value];
    }

    protected function view(): string
    {
        return 'hrm::livewire.employees.documents';
    }
}
