<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Core\Livewire\Table\SelectFilter;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Services\EmployeeVisibility;
use App\Modules\Payroll\Enums\AttendanceStatus;
use App\Modules\Payroll\Models\AttendanceDay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * Payroll → Attendance → Daily attendance (7.5): the processed days (attendance_days), newest first. [TA-05]
 * Only employees the user can see. Read-only: a wrong day is fixed by a correction (7.6) or by processing again.
 */
class AttendanceDayTable extends DataTable
{
    /** @var array<string, string> */
    protected $listeners = ['attendance-days-saved' => '$refresh'];

    /** @return Builder<AttendanceDay> */
    protected function query(): Builder
    {
        // whereHas('employee') goes through Employee's visibility scope: only employees this user may see.
        return AttendanceDay::query()
            ->with(['employee:id,code,first_name,last_name', 'shift:id,code'])
            ->whereHas('employee');
    }

    protected function defaultSort(): array
    {
        return ['date' => 'desc', 'employee_id' => 'asc'];
    }

    protected function filters(): array
    {
        // This month and the twelve before it, as "2026-12" => "December 2026".
        $months = [];
        for ($m = now()->startOfMonth(), $i = 0; $i < 13; $i++, $m = $m->subMonth()) {
            $months[$m->format('Y-m')] = $m->translatedFormat('F Y');
        }

        return [
            SelectFilter::make('month', __('Month'), $months)->query(function (Builder $q, string $month) {
                $start = Carbon::createFromFormat('!Y-m', $month);
                $q->whereBetween('date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()]);
            }),
            SelectFilter::make('status', __('Status'), $this->stringKeys(AttendanceStatus::options())),
            SelectFilter::make('org_unit', __('Org unit'), $this->stringKeys(app(EmployeeVisibility::class)->orgUnitOptionsFor(Auth::user())))
                ->query(fn (Builder $q, string $id) => $q->whereHas('employee', fn (Builder $e) => $e
                    ->whereIn('org_unit_id', [(int) $id, ...(OrgUnit::find((int) $id)?->descendantIds() ?? [])]))),
        ];
    }

    protected function columns(): array
    {
        $time = fn (?Carbon $at) => $at === null ? '—' : $at->format('H:i');
        $hours = fn (int $minutes) => $minutes === 0 ? '—' : AttendanceDay::hoursLabel($minutes);

        return [
            Column::make(__('Date'), 'date')->sortable()->alwaysVisible()
                ->format(fn (AttendanceDay $d) => format_date($d->date).' · '.$d->date->translatedFormat('D')),
            Column::make(__('Employee'), 'employee_id')->alwaysVisible()
                ->format(fn (AttendanceDay $d) => $d->employee ? $d->employee->code.' · '.$d->employee->name : '—'),
            Column::make(__('Shift'), 'shift_id')->format(fn (AttendanceDay $d) => $d->shift->code ?? '—'),
            Column::make(__('Status'), 'status')->sortable()->format(fn (AttendanceDay $d) => new HtmlString(Blade::render(
                '<x-ui.badge :color="$d->status->color()">{{ $d->status->label() }}</x-ui.badge>'
                .'@if ($d->is_late) <x-ui.badge color="warning">{{ __(\'Late\') }}</x-ui.badge>@endif'
                .'@if ($d->is_corrected) <x-ui.badge color="info">{{ __(\'Corrected\') }}</x-ui.badge>@endif'
                .'@if ($d->is_locked) <x-ui.badge color="neutral">{{ __(\'Locked\') }}</x-ui.badge>@endif',
                ['d' => $d],
            ))),
            Column::make(__('In'), 'in_at')->format(fn (AttendanceDay $d) => $time($d->in_at)),
            Column::make(__('Out'), 'out_at')->format(fn (AttendanceDay $d) => $time($d->out_at)),
            Column::make(__('Worked'), 'worked_minutes')->sortable()->alignRight()->format(fn (AttendanceDay $d) => $hours($d->worked_minutes)),
            Column::make(__('Late'), 'late_minutes')->sortable()->alignRight()->format(fn (AttendanceDay $d) => $hours($d->late_minutes)),
            Column::make(__('Early exit'), 'early_exit_minutes')->alignRight()->format(fn (AttendanceDay $d) => $hours($d->early_exit_minutes)),
            Column::make(__('Overtime'), 'ot_minutes')->sortable()->alignRight()->format(fn (AttendanceDay $d) => $hours($d->ot_minutes)),
            Column::make(__('Note'), 'note')->format(fn (AttendanceDay $d) => $d->note ?? ''),
            Column::make(__('Processed'), 'processed_at')->format(fn (AttendanceDay $d) => $d->processed_at ? format_datetime($d->processed_at) : '—'),
        ];
    }
}
