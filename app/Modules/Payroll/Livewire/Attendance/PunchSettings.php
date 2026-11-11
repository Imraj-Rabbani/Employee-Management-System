<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Self Service web punch settings (company settings, 7.3e), shown on the Devices tab. [TA-04]
 *
 *   attendance.web_punch          may employees punch in the browser (My Attendance)?   default: no
 *   attendance.web_punch_location must the browser send its location?                   default: yes
 *
 * Where it is allowed is the shift's geofence (Shifts tab). Without a geofence, any place is fine.
 */
class PunchSettings extends Component
{
    public bool $webPunch = false;

    public bool $requireLocation = true;

    public function mount(): void
    {
        $this->webPunch = (bool) settings('attendance.web_punch', false);
        $this->requireLocation = (bool) settings('attendance.web_punch_location', true);
    }

    public function save(): void
    {
        Gate::authorize('payroll.attendance.setup');
        settings()->setMany([
            'attendance.web_punch' => $this->webPunch,
            'attendance.web_punch_location' => $this->requireLocation,
        ]);

        $this->dispatch('toast', message: __('Web punch settings saved.'), variant: 'success');
    }

    public function render(): View
    {
        return view('payroll::livewire.attendance.punch-settings');
    }
}
