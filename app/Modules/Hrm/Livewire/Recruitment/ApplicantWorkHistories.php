<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

/** Applicant profile → "Work history" card. Same fields and rules as the employee's. [REC-03] */
class ApplicantWorkHistories extends ApplicantRecords
{
    protected function relation(): string
    {
        return 'workHistories';
    }

    protected function defaults(): array
    {
        return ['organization' => '', 'designation' => '', 'from_date' => '', 'to_date' => '', 'leaving_reason' => ''];
    }

    protected function fieldRules(): array
    {
        return [
            'organization' => ['required', 'string', 'max:300'],
            'designation' => ['nullable', 'string', 'max:100'],
            'from_date' => ['nullable', 'date', 'before_or_equal:today'],
            'to_date' => ['nullable', 'date', 'after_or_equal:form.from_date'],
            'leaving_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function labels(): array
    {
        return ['from_date' => __('from'), 'to_date' => __('to'), 'leaving_reason' => __('reason for leaving')];
    }

    protected function view(): string
    {
        return 'hrm::livewire.recruitment.applicant-work-histories';
    }
}
