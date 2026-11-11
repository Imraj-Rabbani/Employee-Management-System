<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Core\Livewire\Table\SelectFilter;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Models\AttendanceCorrection;
use App\Modules\Workflow\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * Payroll → Attendance → Corrections (7.6): every correction of the employees the user can see, newest first.
 * Click the number of a correction that was sent back to change it and send it again. [TA-06]
 */
class CorrectionTable extends DataTable
{
    /** @var array<string, string> */
    protected $listeners = ['corrections-saved' => '$refresh'];

    /** @return Builder<AttendanceCorrection> */
    protected function query(): Builder
    {
        // Employee::query() has the visibility scope (the relation itself does not: approvers need it).
        return AttendanceCorrection::query()
            ->with(['employee:id,code,first_name,last_name', 'approvals'])
            ->whereIn('employee_id', Employee::query()->select('id'));
    }

    protected function defaultSort(): array
    {
        return ['id' => 'desc'];
    }

    protected function filters(): array
    {
        $months = [];
        for ($m = now()->startOfMonth(), $i = 0; $i < 13; $i++, $m = $m->subMonth()) {
            $months[$m->format('Y-m')] = $m->translatedFormat('F Y');
        }
        $pending = fn (Builder $a) => $a->where('status', ApprovalStatus::Pending);

        return [
            SelectFilter::make('month', __('Month'), $months)->query(function (Builder $q, string $month) {
                $start = Carbon::createFromFormat('!Y-m', $month) ?: now();
                $q->whereBetween('date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()]);
            }),
            SelectFilter::make('status', __('Status'), ['waiting' => __('Waiting for approval'), 'approved' => __('Approved'), 'other' => __('Sent back or rejected')])
                ->query(fn (Builder $q, string $v) => match ($v) {
                    'approved' => $q->whereNotNull('approved_at'),
                    'waiting' => $q->whereHas('approvals', $pending),
                    default => $q->whereNull('approved_at')->whereDoesntHave('approvals', $pending),
                }),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('No.'), 'id')->sortable()->alwaysVisible()
                ->format(fn (AttendanceCorrection $c) => $c->number())->opens('edit-correction'),
            Column::make(__('Day'), 'date')->sortable()->format(fn (AttendanceCorrection $c) => format_date($c->date).' · '.$c->date->translatedFormat('D')),
            Column::make(__('Employee'), 'employee_id')->alwaysVisible()
                ->format(fn (AttendanceCorrection $c) => $c->employee ? $c->employee->code.' · '.$c->employee->name : '—'),
            Column::make(__('In'), 'requested_in_at')->format(fn (AttendanceCorrection $c) => AttendanceCorrection::timeLabel($c->requested_in_at, $c->date)),
            Column::make(__('Out'), 'requested_out_at')->format(fn (AttendanceCorrection $c) => AttendanceCorrection::timeLabel($c->requested_out_at, $c->date)),
            Column::make(__('Reason'), 'reason')->searchable(),
            Column::make(__('Status'), 'approved_at')->format(function (AttendanceCorrection $c) {
                [$label, $color] = $c->statusBadge();

                return new HtmlString(Blade::render('<x-ui.badge :$color>{{ $label }}</x-ui.badge>', compact('label', 'color')));
            }),
            Column::make(__('Asked on'), 'created_at')->sortable()->format(fn (AttendanceCorrection $c) => format_datetime($c->created_at)),
        ];
    }
}
