<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Payroll\Services\AttendanceCalendar;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * One employee's attendance month as a calendar (7.8). [TA-08]
 * Processed days show their status, in / out, late, corrected and overtime; days not processed yet show the plan
 * (shift, weekend, holiday) so the employee also sees what is coming.
 *
 *   mode "self"  Self Service → My Attendance: the logged-in user's own record
 *   mode "hr"    Payroll → Attendance → Calendar: an employee the user can see (payroll.attendance.process)
 *   mode "team"  Self Service → My Team Attendance: someone in the manager's supervisor chain
 *
 * Who it is, is checked ONCE in mount() and then #[Locked], so the browser cannot switch to someone else.
 */
class EmployeeCalendar extends Component
{
    #[Locked]
    public string $mode = 'self';

    #[Locked]
    public ?int $employeeId = null;

    #[Url(as: 'month')]
    public string $month = '';

    public function mount(string $mode = 'self', ?int $employeeId = null): void
    {
        $this->mode = $mode;
        if ($mode === 'hr') {
            Gate::authorize('payroll.attendance.process');
        }
        $mine = Employee::query()->where('user_id', Auth::id())->first();

        $this->employeeId = match ($mode) {
            'self' => $mine?->id,
            'hr' => Employee::query()->findOrFail((int) $employeeId)->id,   // visibility scope: someone else's → 404
            'team' => $mine !== null && in_array((int) $employeeId, $mine->teamIds(), true) ? (int) $employeeId : abort(404),
            default => abort(404),
        };
        $this->month = AttendanceCalendar::month($this->month ?: null)->format('Y-m');
    }

    public function previous(): void
    {
        $this->month = AttendanceCalendar::month($this->month)->subMonth()->format('Y-m');
    }

    public function next(): void
    {
        $this->month = AttendanceCalendar::month($this->month)->addMonth()->format('Y-m');
    }

    public function thisMonth(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function render(AttendanceCalendar $calendar): View
    {
        // The id was checked in mount() and is locked: no visibility scope needed (a manager may lack HR access).
        $employee = $this->employeeId ? Employee::withoutGlobalScope(VisibleToCurrentUser::class)->find($this->employeeId) : null;
        $month = AttendanceCalendar::month($this->month);
        $weeks = $employee ? $calendar->employeeMonth($employee, $month) : [];

        return view('payroll::livewire.attendance.employee-calendar', [
            'employee' => $employee,
            'monthStart' => $month,
            'weeks' => $weeks,
            'totals' => AttendanceCalendar::totals(collect($weeks)->flatten(1)->where('inMonth', true)->pluck('day')->filter()),
        ]);
    }
}
