<?php

namespace App\Modules\Hrm\Livewire\Leave;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveApplicationDay;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Services\EmployeeVisibility;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * HRM → Leave → Calendar (8.7): who is on leave in a month. Employees × days, only those with leave in the
 * month (approved, or still waiting: shown lighter), for the employees the user can see; filter by org unit
 * (incl. units below) and name / code. The bottom row counts people on leave per day (approved + waiting),
 * to plan cover. Click a day: the application (LeaveApplicationDetails). [LV-04]
 *
 * Attendance's own calendars (7.8) already show approved leave as "L" through ApprovedLeaveCalendar.
 */
class LeaveCalendar extends Component
{
    public const MAX_ROWS = 200;

    #[Url(as: 'month')]
    public string $month = '';

    #[Url(as: 'unit')]
    public string $orgUnitId = '';

    #[Url(as: 'q')]
    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('hrm.leave.manage');
        $this->month = $this->monthStart()->format('Y-m');
    }

    public function previous(): void
    {
        $this->month = $this->monthStart()->subMonth()->format('Y-m');
    }

    public function next(): void
    {
        $this->month = $this->monthStart()->addMonth()->format('Y-m');
    }

    private function monthStart(): CarbonImmutable
    {
        $parsed = preg_match('/^\d{4}-\d{2}$/', $this->month) ? CarbonImmutable::createFromFormat('!Y-m', $this->month) : false;

        return ($parsed ?: CarbonImmutable::now(settings('timezone', config('app.timezone'))))->startOfMonth();
    }

    public function render(): View
    {
        Gate::authorize('hrm.leave.manage');
        $start = $this->monthStart();
        $end = $start->endOfMonth()->startOfDay();

        $units = app(EmployeeVisibility::class)->orgUnitOptionsFor(Auth::user());
        $unitIds = $this->orgUnitId !== '' && isset($units[(int) $this->orgUnitId])
            ? [(int) $this->orgUnitId, ...(OrgUnit::find((int) $this->orgUnitId)?->descendantIds() ?? [])]
            : null;
        $term = trim($this->search);
        $like = '%'.addcslashes($term, '%_\\').'%';

        // Employee::query() keeps the visibility scope.
        $employees = Employee::query()
            ->whereIn('id', LeaveApplicationDay::query()->select('employee_id')->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->whereHas('application', fn ($q) => $q->active()))
            ->when($unitIds, fn ($q, $ids) => $q->whereIn('org_unit_id', $ids))
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('code', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like)))
            ->orderBy('code')
            ->limit(self::MAX_ROWS + 1)
            ->get(['id', 'code', 'first_name', 'last_name']);
        $tooMany = $employees->count() > self::MAX_ROWS;
        $employees = $employees->take(self::MAX_ROWS);

        // employee_id => 'Y-m-d' => the day (with its application and type), in one query.
        $matrix = [];
        $perDay = [];
        LeaveApplicationDay::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('application', fn ($q) => $q->active())
            ->with('application:id,leave_type_id,approved_at', 'application.leaveType:id,code,name')
            ->get()
            ->each(function (LeaveApplicationDay $day) use (&$matrix, &$perDay) {
                $date = $day->date->toDateString();
                $matrix[$day->employee_id][$date] = $day;
                $perDay[$date] = ($perDay[$date] ?? 0) + $day->days;
            });

        $dates = [];
        for ($d = $start; $d->lte($end); $d = $d->addDay()) {
            $dates[] = $d;
        }

        return view('hrm::livewire.leave.leave-calendar', [
            'monthStart' => $start,
            'dates' => $dates,
            'employees' => $employees,
            'matrix' => $matrix,
            'perDay' => $perDay,
            'tooMany' => $tooMany,
            'units' => $units,
            'today' => CarbonImmutable::now(settings('timezone', config('app.timezone')))->toDateString(),
        ]);
    }
}
