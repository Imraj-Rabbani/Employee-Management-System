<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Core\Livewire\LookupForm;
use App\Core\Models\TenantModel;
use App\Modules\Hrm\Models\InterviewQuestion;
use App\Modules\Hrm\Models\QuestionSetItem;
use Illuminate\Validation\Rule;

/**
 * New / Edit / Delete interview question (modal "interview-question-form"), Recruitment → Config.
 * The weight is not here: a question gets its weight in each question set it is part of.
 * Everything else comes from LookupForm.
 */
class InterviewQuestionForm extends LookupForm
{
    protected function key(): string
    {
        return 'interview-question';
    }

    protected function model(): string
    {
        return InterviewQuestion::class;
    }

    protected function defaults(): array
    {
        return ['name' => '', 'sequence' => 1, 'description' => '', 'is_active' => true];
    }

    protected function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(InterviewQuestion::class, 'name')->ignore($this->recordId)],
            'sequence' => ['required', 'integer', 'min:0', 'max:9999'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }

    protected function labels(): array
    {
        return ['name' => __('question')];
    }

    /** In a question set: take it out of the set first (or make the question inactive). */
    protected function deleteProblem(TenantModel $record): ?string
    {
        return QuestionSetItem::where('interview_question_id', $record->getKey())->exists()
            ? __('It is in a question set. Remove it from the set first, or make it inactive.')
            : null;
    }

    protected function view(): string
    {
        return 'hrm::livewire.recruitment.interview-question-form';
    }
}
