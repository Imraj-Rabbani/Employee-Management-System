<?php

namespace App\Modules\Payroll\Livewire;

use App\Core\Livewire\LookupForm;
use App\Modules\Payroll\Models\PayScaleProfile;
use Illuminate\Validation\Rule;

/** New / Edit / Delete pay scale profile (modal "pay-scale-profile-form"). */
class PayScaleProfileForm extends LookupForm
{
    protected function key(): string
    {
        return 'pay-scale-profile';
    }

    protected function model(): string
    {
        return PayScaleProfile::class;
    }

    protected function defaults(): array
    {
        return [
            'code' => '', 'name' => '', 'pays_arrear' => true, 'threshold_day' => 0,
            'deduct_lwp' => true, 'ignore_late_lwp' => false, 'detail' => '', 'is_active' => true,
        ];
    }

    protected function fieldRules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique(PayScaleProfile::class, 'code')->ignore($this->recordId)],
            'name' => ['required', 'string', 'max:100', Rule::unique(PayScaleProfile::class, 'name')->ignore($this->recordId)],
            'pays_arrear' => ['boolean'],
            'threshold_day' => ['required', 'integer', 'between:0,28'],   // 28: every month has that day
            'deduct_lwp' => ['boolean'],
            'ignore_late_lwp' => ['boolean'],
            'detail' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    protected function labels(): array
    {
        return ['threshold_day' => __('threshold day')];
    }

    /** "Ignore LWP from lates" means nothing when LWP is not deducted at all: switched off with it. */
    public function save(): void
    {
        if (! ($this->form['deduct_lwp'] ?? true)) {
            $this->form['ignore_late_lwp'] = false;
        }
        parent::save();
    }

    protected function view(): string
    {
        return 'payroll::livewire.pay-scale-profile-form';
    }
}
