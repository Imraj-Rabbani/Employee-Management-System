<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Core\Livewire\LookupForm;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Payroll\Exceptions\DeviceException;
use App\Modules\Payroll\Models\Device;
use App\Modules\Payroll\Services\DeviceSync;
use Illuminate\Validation\Rule;

/**
 * New / Edit / Delete device (modal "device-form"), and "Sync now" for one device. [TA-04]
 * Deleting a device keeps its punches (punch_logs.device_id becomes NULL).
 */
class DeviceForm extends LookupForm
{
    protected function key(): string
    {
        return 'device';
    }

    protected function model(): string
    {
        return Device::class;
    }

    protected function defaults(): array
    {
        return ['name' => '', 'serial_number' => '', 'ip' => '', 'port' => '4370', 'org_unit_id' => '', 'is_active' => true];
    }

    protected function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique(Device::class, 'name')->ignore($this->recordId)],
            'serial_number' => ['nullable', 'string', 'max:100', Rule::unique(Device::class, 'serial_number')->ignore($this->recordId)],
            'ip' => ['required', 'ip'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'org_unit_id' => ['nullable', Rule::exists(OrgUnit::class, 'id')],
            'is_active' => ['boolean'],
        ];
    }

    protected function labels(): array
    {
        return ['ip' => __('IP address'), 'org_unit_id' => __('org unit'), 'serial_number' => __('serial number')];
    }

    /** Pulls this device's punches right away (same as the 5-minute schedule) and says how it went. */
    public function syncNow(DeviceSync $sync): void
    {
        $device = Device::findOrFail($this->recordId);

        try {
            $stored = $sync->sync($device);
            $this->dispatch('toast', message: trans_choice(':count new punch stored.|:count new punches stored.', $stored), variant: 'success');
        } catch (DeviceException $e) {
            $this->dispatch('toast', message: $e->getMessage(), variant: 'danger');
        }
        $this->dispatch('device-saved');   // the list shows the new "Last sync"
    }

    protected function viewData(): array
    {
        return ['units' => OrgUnit::pathOptions()];
    }

    protected function view(): string
    {
        return 'payroll::livewire.attendance.device-form';
    }
}
