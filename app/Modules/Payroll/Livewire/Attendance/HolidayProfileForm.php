<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Core\Livewire\LookupForm;
use App\Modules\Payroll\Models\HolidayProfile;
use Illuminate\Validation\Rule;

/**
 * New / Edit / Delete holiday calendar (modal "holiday-profile-form"). [TA-02]
 * Which holidays are in it is chosen on each holiday (HolidayForm): there are few calendars and many holidays.
 */
class HolidayProfileForm extends LookupForm
{
    protected function key(): string
    {
        return 'holiday-profile';
    }

    protected function model(): string
    {
        return HolidayProfile::class;
    }

    protected function defaults(): array
    {
        return ['name' => '', 'detail' => '', 'is_active' => true];
    }

    protected function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique(HolidayProfile::class, 'name')->ignore($this->recordId)],
            'detail' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    protected function view(): string
    {
        return 'payroll::livewire.attendance.holiday-profile-form';
    }
}
