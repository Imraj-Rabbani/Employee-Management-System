<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Core\Livewire\LookupForm;
use App\Modules\Payroll\Enums\Weekday;
use App\Modules\Payroll\Enums\WeekParity;
use App\Modules\Payroll\Models\WeekendProfile;
use Illuminate\Validation\Rule;

/** New / Edit / Delete weekend profile (modal "weekend-profile-form"). [TA-02] */
class WeekendProfileForm extends LookupForm
{
    protected function key(): string
    {
        return 'weekend-profile';
    }

    protected function model(): string
    {
        return WeekendProfile::class;
    }

    protected function defaults(): array
    {
        return [
            'code' => '', 'name' => '',
            'day_one' => (string) Weekday::Friday->value, 'alternate_one' => '',
            'day_two' => '', 'alternate_two' => '',
            'home_office_day' => '', 'detail' => '', 'is_active' => true,
        ];
    }

    protected function fieldRules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:50', Rule::unique(WeekendProfile::class, 'code')->ignore($this->recordId)],
            'name' => ['required', 'string', 'max:100', Rule::unique(WeekendProfile::class, 'name')->ignore($this->recordId)],
            'day_one' => ['nullable', 'integer', Rule::enum(Weekday::class)],
            'alternate_one' => ['nullable', 'integer', Rule::enum(WeekParity::class)],
            'day_two' => ['nullable', 'integer', Rule::enum(Weekday::class), 'different:form.day_one'],
            'alternate_two' => ['nullable', 'integer', Rule::enum(WeekParity::class)],
            'home_office_day' => ['nullable', 'integer', Rule::enum(Weekday::class)],
            'detail' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    protected function labels(): array
    {
        return [
            'day_one' => __('first weekend day'), 'day_two' => __('second weekend day'),
            'alternate_one' => __('weeks'), 'alternate_two' => __('weeks'), 'home_office_day' => __('home office day'),
        ];
    }

    protected function check(array $values): bool
    {
        // "Odd weeks" without a day would mean nothing.
        foreach (['one', 'two'] as $n) {
            if ($values["alternate_{$n}"] !== null && $values["day_{$n}"] === null) {
                $this->addError("form.alternate_{$n}", __('Choose the day first.'));
            }
        }
        if ($values['home_office_day'] !== null && in_array($values['home_office_day'], [$values['day_one'], $values['day_two']], false)) {
            $this->addError('form.home_office_day', __('The home office day cannot be a weekend day.'));
        }

        return $this->getErrorBag()->isEmpty();
    }

    protected function viewData(): array
    {
        return ['days' => Weekday::options(), 'parities' => WeekParity::options()];
    }

    protected function view(): string
    {
        return 'payroll::livewire.attendance.weekend-profile-form';
    }
}
