<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Core\Livewire\LookupForm;
use App\Modules\Hrm\Models\InterviewFactor;
use Illuminate\Validation\Rule;

/**
 * New / Edit / Delete interview factor (modal "interview-factor-form"). [REC-05]
 * Everything except the fields and the weight check comes from LookupForm.
 */
class InterviewFactorForm extends LookupForm
{
    protected function key(): string
    {
        return 'interview-factor';
    }

    protected function model(): string
    {
        return InterviewFactor::class;
    }

    protected function defaults(): array
    {
        return ['name' => '', 'weight' => 0, 'sequence' => 1, 'is_required' => false, 'description' => '', 'is_active' => true];
    }

    protected function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique(InterviewFactor::class, 'name')->ignore($this->recordId)],
            'weight' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'sequence' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_required' => ['boolean'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }

    /** The weights of the ACTIVE factors may not add up to more than 100%. */
    protected function check(array $values): bool
    {
        if (! $values['is_active']) {
            return true;   // an inactive factor is not on the score sheet, so its weight does not count
        }

        $others = $this->otherActiveWeight();
        if ($others + (float) $values['weight'] > 100) {
            $this->addError('form.weight', __('The other active factors already have :used%. This one can have at most :left%.', [
                'used' => $this->percent($others),
                'left' => $this->percent(100 - $others),
            ]));

            return false;
        }

        return true;
    }

    /** Sum of the weights of the active factors, not counting the one being edited. */
    private function otherActiveWeight(): float
    {
        return (float) InterviewFactor::where('is_active', true)
            ->when($this->recordId, fn ($q) => $q->whereKeyNot($this->recordId))
            ->sum('weight');
    }

    private function percent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    protected function viewData(): array
    {
        return ['otherWeight' => $this->percent($this->otherActiveWeight())];
    }

    protected function view(): string
    {
        return 'hrm::livewire.recruitment.interview-factor-form';
    }
}
