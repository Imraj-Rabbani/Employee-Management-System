<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Core\Livewire\Table\SelectFilter;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Models\OvertimeRequest;
use App\Modules\Workflow\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * Payroll → Attendance → Overtime (7.7): every overtime request of the employees the user can see, newest first.
 * Asked / recommended / approved hours side by side. Click the number of a request sent back to change it. [TA-07]
 */
class OvertimeTable extends DataTable
{
    /** @var array<string, string> */
    protected $listeners = ['overtime-saved' => '$refresh'];

    /** @return Builder<OvertimeRequest> */
    protected function query(): Builder
    {
        // Employee::query() has the visibility scope (the relation itself does not: approvers need it).
        return OvertimeRequest::query()
            ->with(['employee:id,code,first_name,last_name', 'shift:id,code', 'approvals'])
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
                ->format(fn (OvertimeRequest $r) => $r->number())->opens('edit-overtime'),
            Column::make(__('Day'), 'date')->sortable()->format(fn (OvertimeRequest $r) => format_date($r->date).' · '.$r->date->translatedFormat('D')),
            Column::make(__('Employee'), 'employee_id')->alwaysVisible()
                ->format(fn (OvertimeRequest $r) => $r->employee ? $r->employee->code.' · '.$r->employee->name : '—'),
            Column::make(__('Shift'), 'shift_id')->format(fn (OvertimeRequest $r) => $r->shift->code ?? '—'),
            Column::make(__('Asked'), 'hours')->sortable()->alignRight()->format(fn (OvertimeRequest $r) => OvertimeRequest::hoursLabel($r->hours)),
            Column::make(__('Recommended'), 'recommended_hours')->alignRight()
                ->format(fn (OvertimeRequest $r) => OvertimeRequest::hoursLabel($r->approved_at ? $r->recommended_hours : $r->recommendedHours())),
            Column::make(__('Approved'), 'approved_hours')->sortable()->alignRight()->format(fn (OvertimeRequest $r) => OvertimeRequest::hoursLabel($r->approved_hours)),
            Column::make(__('Work done'), 'detail')->searchable(),
            Column::make(__('Status'), 'approved_at')->format(function (OvertimeRequest $r) {
                [$label, $color] = $r->statusBadge();

                return new HtmlString(Blade::render('<x-ui.badge :$color>{{ $label }}</x-ui.badge>', compact('label', 'color')));
            }),
        ];
    }
}
