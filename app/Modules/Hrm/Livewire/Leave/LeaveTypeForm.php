<?php

namespace App\Modules\Hrm\Livewire\Leave;

use App\Core\Livewire\LookupForm;
use App\Modules\Hrm\Enums\Gender;
use App\Modules\Hrm\Enums\LeaveBridging;
use App\Modules\Hrm\Enums\LeaveEarnType;
use App\Modules\Hrm\Enums\LeaveEligibleFrom;
use App\Modules\Hrm\Enums\Religion;
use App\Modules\Hrm\Models\LeaveType;
use Illuminate\Validation\Rule;

/**
 * New / Edit / Delete leave type with all its rules (modal "leave-type-form"). [LV-01]
 *
 * Empty number = no limit (saved as NULL). A setting that belongs to a switch (carry-forward limit,
 * post-facto days, encash limit, days worked per earned day) is cleared when its switch is off, so an
 * old hidden value never acts as a rule.
 */
class LeaveTypeForm extends LookupForm
{
    /** switch / choice => the fields that only count when it is on */
    private const DEPENDENT = [
        'allow_post_facto' => ['post_facto_days'],
        'can_carry_forward' => ['carry_forward_limit', 'carry_forward_expiry_months'],
        'is_encashable' => ['encash_limit'],
    ];

    protected function key(): string
    {
        return 'leave-type';
    }

    protected function model(): string
    {
        return LeaveType::class;
    }

    protected function defaults(): array
    {
        return [
            'code' => '', 'name' => '', 'detail' => '', 'sequence' => '0',
            'gender' => '', 'religion' => '',
            'eligible_from' => LeaveEligibleFrom::JoinDate->value, 'eligible_after_months' => '0', 'max_times_in_service' => '',
            'hr_only' => false,
            'min_days' => '', 'max_days' => '', 'max_calendar_days' => '',
            'allow_half_day' => false, 'exclude_holidays' => false, 'bridging' => LeaveBridging::None->value,
            'notice_days' => '0', 'allow_post_facto' => false, 'post_facto_days' => '',
            'reason_required' => false, 'attachment_after_days' => '',
            'is_lwp' => false, 'requires_balance' => true, 'max_balance' => '',
            'earn_type' => LeaveEarnType::Fixed->value, 'earn_per_days_worked' => '',
            'can_carry_forward' => false, 'carry_forward_limit' => '', 'carry_forward_expiry_months' => '',
            'is_encashable' => false, 'encash_limit' => '',
            'is_active' => true,
        ];
    }

    protected function fieldRules(): array
    {
        $days = ['nullable', 'numeric', 'min:0.5', 'max:366', 'multiple_of:0.5'];

        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique(LeaveType::class, 'code')->ignore($this->recordId)],
            'name' => ['required', 'string', 'max:100', Rule::unique(LeaveType::class, 'name')->ignore($this->recordId)],
            'detail' => ['nullable', 'string', 'max:1000'],
            'sequence' => ['required', 'integer', 'min:0', 'max:9999'],

            'gender' => ['nullable', Rule::enum(Gender::class)],
            'religion' => ['nullable', Rule::enum(Religion::class)],
            'eligible_from' => ['required', Rule::enum(LeaveEligibleFrom::class)],
            'eligible_after_months' => ['required', 'integer', 'min:0', 'max:600'],
            'max_times_in_service' => ['nullable', 'integer', 'min:1', 'max:99'],
            'hr_only' => ['boolean'],

            'min_days' => $days,
            'max_days' => $days,
            'max_calendar_days' => ['nullable', 'integer', 'min:1', 'max:366'],
            'allow_half_day' => ['boolean'],
            'exclude_holidays' => ['boolean'],
            'bridging' => ['required', Rule::enum(LeaveBridging::class)],
            'notice_days' => ['required', 'integer', 'min:0', 'max:365'],
            'allow_post_facto' => ['boolean'],
            'post_facto_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'reason_required' => ['boolean'],
            'attachment_after_days' => ['nullable', 'numeric', 'min:0', 'max:366', 'multiple_of:0.5'],

            'is_lwp' => ['boolean'],
            'requires_balance' => ['boolean'],
            'max_balance' => ['nullable', 'numeric', 'min:0.5', 'max:9999', 'multiple_of:0.5'],

            'earn_type' => ['required', Rule::enum(LeaveEarnType::class)],
            'earn_per_days_worked' => [
                Rule::requiredIf(fn () => (int) ($this->form['earn_type'] ?? 0) === LeaveEarnType::PerDaysWorked->value),
                'nullable', 'numeric', 'min:1', 'max:365',
            ],

            'can_carry_forward' => ['boolean'],
            'carry_forward_limit' => ['nullable', 'numeric', 'min:0.5', 'max:9999', 'multiple_of:0.5'],
            'carry_forward_expiry_months' => ['nullable', 'integer', 'min:1', 'max:120'],

            'is_encashable' => ['boolean'],
            'encash_limit' => ['nullable', 'numeric', 'min:0.5', 'max:9999', 'multiple_of:0.5'],

            'is_active' => ['boolean'],
        ];
    }

    protected function labels(): array
    {
        return [
            'eligible_from' => __('counted from'), 'eligible_after_months' => __('eligible after'),
            'max_times_in_service' => __('times in service'),
            'min_days' => __('minimum days'), 'max_days' => __('maximum days'), 'max_calendar_days' => __('maximum calendar days'),
            'notice_days' => __('notice'), 'post_facto_days' => __('days after'), 'attachment_after_days' => __('attachment after'),
            'max_balance' => __('maximum balance'), 'earn_type' => __('earning'), 'earn_per_days_worked' => __('days worked'),
            'carry_forward_limit' => __('carry-forward limit'), 'carry_forward_expiry_months' => __('expires after'),
            'encash_limit' => __('encash limit'),
        ];
    }

    public function save(): void
    {
        foreach (self::DEPENDENT as $switch => $fields) {
            if (! $this->form[$switch]) {
                foreach ($fields as $field) {
                    $this->form[$field] = '';
                }
            }
        }
        if ((int) $this->form['earn_type'] !== LeaveEarnType::PerDaysWorked->value) {
            $this->form['earn_per_days_worked'] = '';
        }

        parent::save();
    }

    protected function check(array $values): bool
    {
        if ($values['min_days'] !== null && $values['max_days'] !== null && (float) $values['min_days'] > (float) $values['max_days']) {
            $this->addError('form.max_days', __('The maximum cannot be below the minimum.'));
        }
        if (! $values['allow_half_day']) {
            foreach (['min_days', 'max_days'] as $field) {
                if ($values[$field] !== null && fmod((float) $values[$field], 1.0) !== 0.0) {
                    $this->addError("form.{$field}", __('Half days are not allowed for this leave type: use whole days.'));
                }
            }
        }

        return $this->getErrorBag()->isEmpty();
    }

    protected function viewData(): array
    {
        return [
            'genders' => Gender::options(),
            'religions' => Religion::options(),
            'eligibleFrom' => LeaveEligibleFrom::options(),
            'bridgings' => LeaveBridging::options(),
            'earnTypes' => LeaveEarnType::options(),
            'perDaysWorked' => LeaveEarnType::PerDaysWorked->value,
        ];
    }

    protected function view(): string
    {
        return 'hrm::livewire.leave.leave-type-form';
    }
}
