<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Services\EmployeeVisibility;
use App\Modules\Payroll\Services\AttendanceCalendar;
use App\Modules\Payroll\Services\AttendanceReprocessor;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * A team's attendance month: employees × days, one letter per processed day (7.8). [TA-08, ESS-04]
 *
 *   mode "hr"    Payroll → Attendance → Calendar: employees the user can see, filter by org unit and name / code
 *   mode "team"  Self Service → My Team Attendance: everyone below the manager in the supervisor chain
 *
 * Only people employed at some point in the month. At most MAX_ROWS rows: narrow with the filters.
 * A name opens that employee's own calendar.
 */
class TeamCalendar extends Component
{
    public const MAX_ROWS = 150;

    #[Locked]
    public string $mode = 'hr';

    #[Url(as: 'month')]
    public string $month = '';

    #[Url(as: 'unit')]
    public string $orgUnitId = '';

    #[Url(as: 'q')]
    public string $search = '';

    public function mount(string $mode = 'hr'): void
    {
        $this->mode = in_array($mode, ['hr', 'team'], true) ? $mode : abort(404);
        if ($mode === 'hr') {
            Gate::authorize('payroll.attendance.process');
        }
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

    public function render(AttendanceCalendar $calendar, AttendanceReprocessor $reprocessor): View
    {
        $month = AttendanceCalendar::month($this->month);
        $from = $month->startOfMonth()->toDateString();
        $to = $month->endOfMonth()->toDateString();

        if ($this->mode === 'hr') {
            $units = app(EmployeeVisibility::class)->orgUnitOptionsFor(Auth::user());
            $unitIds = $this->orgUnitId !== '' && isset($units[(int) $this->orgUnitId])
                ? [(int) $this->orgUnitId, ...(OrgUnit::find((int) $this->orgUnitId)?->descendantIds() ?? [])]
                : null;
            $query = $reprocessor->employedBetween($from, $to)   // visibility scope stays on
                ->when($unitIds, fn ($q, $ids) => $q->whereIn('org_unit_id', $ids));
        } else {
            $units = [];
            $me = Employee::query()->where('user_id', Auth::id())->first();
            $query = $reprocessor->everyoneEmployedBetween($from, $to)->whereIn('id', $me?->teamIds() ?: [0]);
        }

        $term = trim($this->search);
        $employees = $query
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('code', 'like', '%'.addcslashes($term, '%_\\').'%')
                ->orWhere('first_name', 'like', '%'.addcslashes($term, '%_\\').'%')
                ->orWhere('last_name', 'like', '%'.addcslashes($term, '%_\\').'%')))
            ->orderBy('code')
            ->limit(self::MAX_ROWS + 1)
            ->get(['id', 'code', 'first_name', 'last_name']);
        $tooMany = $employees->count() > self::MAX_ROWS;
        $employees = $employees->take(self::MAX_ROWS);

        $dates = [];
        for ($d = $month->startOfMonth(); $d->lte($month->endOfMonth()); $d = $d->addDay()) {
            $dates[] = $d;
        }

        return view('payroll::livewire.attendance.team-calendar', [
            'monthStart' => $month,
            'dates' => $dates,
            'employees' => $employees,
            'matrix' => $calendar->teamMonth($employees, $month),
            'tooMany' => $tooMany,
            'units' => $units,
            'today' => $reprocessor->today(),
        ]);
    }

    /** Where a name in the grid leads: that employee's own calendar, same month. */
    public function employeeUrl(int $id): string
    {
        $route = $this->mode === 'hr' ? 'payroll.attendance.calendar' : 'ess.team-attendance';

        return route($route, ['employee' => $id, 'month' => $this->month]);
    }
}
