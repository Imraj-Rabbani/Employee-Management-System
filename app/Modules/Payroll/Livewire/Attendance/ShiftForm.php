<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Core\Livewire\LookupForm;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Payroll\Models\Shift;
use Illuminate\Validation\Rule;

/**
 * New / Edit / Delete shift (modal "shift-form"). [TA-01]
 *
 * "Overnight" is not on the form: the model works it out from the times (ends before it starts).
 * Ticking "Default" takes the flag away from the old default shift (also done by the model).
 */
class ShiftForm extends LookupForm
{
    private const TIMES = ['start_time', 'end_time', 'half_day_after'];

    protected function key(): string
    {
        return 'shift';
    }

    protected function model(): string
    {
        return Shift::class;
    }

    protected function defaults(): array
    {
        return [
            'code' => '', 'name' => '', 'org_unit_id' => '',
            'start_time' => '09:00', 'end_time' => '18:00', 'hours' => '8',
            'grace_minutes' => '0', 'grace_limit_per_month' => '', 'flexibility_minutes' => '0',
            'half_day_hours' => '', 'half_day_after' => '',
            'is_default' => false,
            'geofence_enabled' => false, 'geofence_lat' => '', 'geofence_lng' => '', 'geofence_radius_m' => '',
            'is_active' => true,
        ];
    }

    /** The database gives "09:00:00"; <input type="time"> and our H:i rule want "09:00". */
    public function edit(int $id): void
    {
        parent::edit($id);

        foreach (self::TIMES as $field) {
            if ($this->form[$field] !== '') {
                $this->form[$field] = substr((string) $this->form[$field], 0, 5);
            }
        }
    }

    protected function fieldRules(): array
    {
        $geofence = Rule::requiredIf(fn () => (bool) ($this->form['geofence_enabled'] ?? false));

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique(Shift::class, 'code')->ignore($this->recordId)],
            'name' => ['required', 'string', 'max:100'],
            'org_unit_id' => ['nullable', Rule::exists(OrgUnit::class, 'id')],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'different:form.start_time'],
            'hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'grace_limit_per_month' => ['nullable', 'integer', 'min:0', 'max:31'],
            'flexibility_minutes' => ['required', 'integer', 'min:0', 'max:480'],
            'half_day_hours' => ['nullable', 'numeric', 'min:0.5', 'lt:form.hours'],
            'half_day_after' => ['nullable', 'date_format:H:i'],
            'is_default' => ['boolean'],
            'geofence_enabled' => ['boolean'],
            'geofence_lat' => [$geofence, 'nullable', 'numeric', 'between:-90,90'],
            'geofence_lng' => [$geofence, 'nullable', 'numeric', 'between:-180,180'],
            'geofence_radius_m' => [$geofence, 'nullable', 'integer', 'min:10', 'max:100000'],
            'is_active' => ['boolean'],
        ];
    }

    protected function labels(): array
    {
        return [
            'org_unit_id' => __('org unit'), 'start_time' => __('start'), 'end_time' => __('end'),
            'grace_minutes' => __('grace'), 'grace_limit_per_month' => __('grace limit'), 'flexibility_minutes' => __('flexibility'),
            'half_day_hours' => __('half day below'), 'half_day_after' => __('half day after'),
            'geofence_lat' => __('latitude'), 'geofence_lng' => __('longitude'), 'geofence_radius_m' => __('radius'),
        ];
    }

    protected function check(array $values): bool
    {
        if ($values['is_default'] && ! $values['is_active']) {
            $this->addError('form.is_default', __('The default shift must be active.'));

            return false;
        }

        return true;
    }

    protected function viewData(): array
    {
        return ['units' => OrgUnit::pathOptions()];
    }

    protected function view(): string
    {
        return 'payroll::livewire.attendance.shift-form';
    }
}
