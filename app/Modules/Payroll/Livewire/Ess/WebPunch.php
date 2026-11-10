<?php

namespace App\Modules\Payroll\Livewire\Ess;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Enums\PunchSource;
use App\Modules\Payroll\Models\PunchLog;
use App\Modules\Payroll\Models\RosterItem;
use App\Modules\Payroll\Models\Shift;
use App\Modules\Payroll\Services\GeoDistance;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Self Service → My Attendance: "Punch now" in the browser, and my punches of the last days. (7.3e) [TA-04]
 *
 * Rules (checked on the server; the browser only sends its location):
 *   - the company allows web punch (Payroll → Attendance → Devices → Web punch)
 *   - the user has an employee record (5.7) — punches are always their OWN
 *   - location required (company setting) → refused without one
 *   - today's shift (approved roster, else own shift, else the default) has a geofence → must be within its radius
 *   - not twice within a minute (double clicks)
 */
class WebPunch extends Component
{
    public const MIN_GAP_SECONDS = 60;

    public function punch(?float $latitude = null, ?float $longitude = null): void
    {
        $employee = $this->mine();
        if ($employee === null || ! settings('attendance.web_punch', false)) {
            $this->refuse(__('Web punch is not available for you.'));

            return;
        }

        $hasLocation = $latitude !== null && $longitude !== null
            && $latitude >= -90 && $latitude <= 90 && $longitude >= -180 && $longitude <= 180;
        $shift = $this->todaysShift($employee);
        $fenced = $shift?->geofence_enabled && $shift->geofence_lat !== null && $shift->geofence_lng !== null && $shift->geofence_radius_m;

        if (! $hasLocation && ($fenced || settings('attendance.web_punch_location', true))) {
            $this->refuse(__('Your location is needed to punch. Allow location access in the browser and try again.'));

            return;
        }

        $distance = null;
        if ($fenced) {   // a fenced punch without a location was refused above
            $distance = GeoDistance::metres($latitude, $longitude, (float) $shift->geofence_lat, (float) $shift->geofence_lng);
            if ($distance > $shift->geofence_radius_m) {
                $this->refuse(__('You are :distance m away from your workplace. Punching is allowed within :radius m.', [
                    'distance' => number_format($distance), 'radius' => number_format($shift->geofence_radius_m),
                ]));

                return;
            }
        }

        $recent = PunchLog::query()->where('employee_id', $employee->id)
            ->where('punched_at', '>', now()->subSeconds(self::MIN_GAP_SECONDS))->exists();
        if ($recent) {
            $this->refuse(__('You punched less than a minute ago.'));

            return;
        }

        $punch = PunchLog::create([
            'employee_id' => $employee->id,
            'access_code' => $employee->access_code,
            'punched_at' => now()->startOfSecond(),
            'source' => PunchSource::Web,
            'latitude' => $hasLocation ? $latitude : null,
            'longitude' => $hasLocation ? $longitude : null,
            'distance_m' => $distance,
            'created_by_user_id' => Auth::id(),
        ]);

        $this->dispatch('toast', message: __('Punched at :time.', ['time' => $punch->punched_at->format('H:i')]), variant: 'success');
    }

    /** The employee record linked to this login, or null (no web punch then). */
    private function mine(): ?Employee
    {
        return Employee::query()->where('user_id', Auth::id())->first();
    }

    /** Today's plan: approved roster cell, else the employee's own shift, else the company default. */
    private function todaysShift(Employee $employee): ?Shift
    {
        $plan = RosterItem::planFor($employee, now());
        if ($plan?->shift_id !== null) {
            return $plan->shift;
        }

        return $employee->shift ?? Shift::default();
    }

    private function refuse(string $message): void
    {
        $this->dispatch('toast', message: $message, variant: 'danger');
    }

    public function render(): View
    {
        $employee = $this->mine();
        $shift = $employee ? $this->todaysShift($employee) : null;

        return view('payroll::livewire.ess.web-punch', [
            'employee' => $employee,
            'enabled' => (bool) settings('attendance.web_punch', false),
            'needsLocation' => (bool) settings('attendance.web_punch_location', true) || (bool) $shift?->geofence_enabled,
            'shift' => $shift,
            // My punches of the last 7 days, newest day first.
            'days' => $employee === null ? collect() : PunchLog::query()
                ->where('employee_id', $employee->id)
                ->where('punched_at', '>=', now()->subDays(6)->startOfDay())
                ->orderBy('punched_at')
                ->get()
                ->groupBy(fn (PunchLog $p) => $p->punched_at->toDateString())
                ->sortKeysDesc(),
        ]);
    }
}
