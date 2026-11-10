<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Core\Livewire\LookupForm;
use App\Modules\Hrm\Models\RejectionReason;
use Illuminate\Validation\Rule;

/**
 * New / Edit / Delete rejection reason (modal "rejection-reason-form"). [REC-04]
 * Everything except the fields comes from LookupForm.
 */
class RejectionReasonForm extends LookupForm
{
    protected function key(): string
    {
        return 'rejection-reason';
    }

    protected function model(): string
    {
        return RejectionReason::class;
    }

    protected function defaults(): array
    {
        return ['name' => '', 'is_active' => true];
    }

    protected function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique(RejectionReason::class, 'name')->ignore($this->recordId)],
            'is_active' => ['boolean'],
        ];
    }

    protected function view(): string
    {
        return 'hrm::livewire.recruitment.rejection-reason-form';
    }
}
