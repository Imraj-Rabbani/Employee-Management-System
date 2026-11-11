<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Services\EmployeeVisibility;
use App\Modules\Payroll\Services\AttendanceReprocessor;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * "Process again" on the Daily attendance tab (7.5): HR picks a date range and, if they like, one org unit
 * or one employee; one ProcessAttendance job per employee is queued. [TA-06]
 *
 * Only employees the user can see. Locked days (closed payroll) and future days are not changed.
 * Use after changing a shift, a weekend profile or an employee's calendar: those changes do not
 * reprocess by themselves (rosters, holidays and punches do).
 */
class AttendanceReprocess extends Component
{
    /** Longest range at once: about three months (one job per employee either way, but each job gets slower). */
    public const MAX_DAYS = 93;

    public string $from = '';

    public string $to = '';

    public string $orgUnitId = '';

    public string $employeeId = '';

    /** The modal opens with last month … yesterday: the usual "before payroll" check. */
    public function mount(): void
    {
        $yesterday = Carbon::parse(app(AttendanceReprocessor::class)->today())->subDay();
        $this->from = $yesterday->copy()->startOfMonth()->toDateString();
        $this->to = $yesterday->toDateString();
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $today = app(AttendanceReprocessor::class)->today();

        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:'.$today],
            'orgUnitId' => ['nullable', Rule::in(array_map('strval', array_keys($this->orgUnits())))],
            'employeeId' => ['nullable', Rule::in(array_map('strval', array_keys($this->employees())))],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['from' => __('from'), 'to' => __('to'), 'orgUnitId' => __('org unit'), 'employeeId' => __('employee')];
    }

    public function run(AttendanceReprocessor $reprocessor): void
    {
        Gate::authorize('payroll.attendance.process');
        $this->validate();
        if ((int) Carbon::parse($this->from)->diffInDays(Carbon::parse($this->to)) + 1 > self::MAX_DAYS) {
            $this->addError('to', __('At most :days days at once.', ['days' => self::MAX_DAYS]));

            return;
        }

        // employedBetween() keeps the visibility scope: only employees this user may see.
        $unitIds = $this->orgUnitId === '' ? null
            : [(int) $this->orgUnitId, ...(OrgUnit::find((int) $this->orgUnitId)?->descendantIds() ?? [])];
        $ids = $reprocessor->employedBetween($this->from, $this->to)
            ->when($unitIds, fn ($q, $ids) => $q->whereIn('org_unit_id', $ids))
            ->when($this->employeeId !== '', fn ($q) => $q->whereKey((int) $this->employeeId))
            ->pluck('id');

        $count = $reprocessor->queue($ids, $this->from, $this->to);

        Flux::modal('attendance-reprocess')->close();
        $this->dispatch('attendance-days-saved');
        $range = format_date(Carbon::parse($this->from)).' – '.format_date(Carbon::parse($this->to));
        $this->dispatch('toast', variant: 'success', message: match (true) {
            $count === 0 => __('Nobody to process: no employee was employed in :range.', ['range' => $range]),
            config('queue.default') === 'sync' => trans_choice(':count employee processed, :range.|:count employees processed, :range.', $count, ['range' => $range]),
            default => trans_choice(':count employee queued for :range. The results appear here in a few minutes.|:count employees queued for :range. The results appear here in a few minutes.', $count, ['range' => $range]),
        });
    }

    /** @return array<int, string> id => "Head Office › Finance" (units the user can see) */
    private function orgUnits(): array
    {
        return app(EmployeeVisibility::class)->orgUnitOptionsFor(Auth::user());
    }

    /** @return array<int, string> id => "E-0012 · Karim Ahmed" (employees the user can see, also those who left) */
    private function employees(): array
    {
        return Employee::query()->orderBy('code')->get(['id', 'code', 'first_name', 'last_name'])
            ->mapWithKeys(fn (Employee $e) => [$e->id => $e->code.' · '.$e->name])->all();
    }

    public function render(): View
    {
        return view('payroll::livewire.attendance.attendance-reprocess', [
            'units' => $this->orgUnits(),
            'employees' => $this->employees(),
            'today' => app(AttendanceReprocessor::class)->today(),
        ]);
    }
}
