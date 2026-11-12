<?php

namespace App\Modules\Hrm\Livewire\Leave;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Core\Livewire\Table\SelectFilter;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveApplication;
use App\Modules\Hrm\Models\LeaveType;
use App\Modules\Workflow\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * HRM → Leave → Applications (8.4): every leave application of the employees the user can see, newest first.
 * Click the number to open it (details, timeline, change / withdraw). [LV-04]
 */
class LeaveApplicationTable extends DataTable
{
    /** @var array<string, string> */
    protected $listeners = ['leave-saved' => '$refresh'];

    /** @return Builder<LeaveApplication> */
    protected function query(): Builder
    {
        // Employee::query() has the visibility scope (the relation itself does not: approvers need it).
        return LeaveApplication::query()
            ->with(['employee:id,code,first_name,last_name', 'leaveType:id,code,name', 'approvals'])
            ->whereIn('employee_id', Employee::query()->select('id'));
    }

    protected function defaultSort(): array
    {
        return ['id' => 'desc'];
    }

    protected function filters(): array
    {
        $months = [];
        for ($m = now()->addMonths(2)->startOfMonth(), $i = 0; $i < 15; $i++, $m = $m->subMonth()) {
            $months[$m->format('Y-m')] = $m->translatedFormat('F Y');
        }
        $pending = fn (Builder $a) => $a->where('status', ApprovalStatus::Pending);
        // The filter's Builder is untyped: the model's own scope, as an id subquery, keeps ONE definition of "active".
        $active = fn (Builder $q) => $q->whereIn('id', LeaveApplication::query()->active()->select('id'));

        return [
            SelectFilter::make('month', __('Month'), $months)->query(function (Builder $q, string $month) {
                $start = Carbon::createFromFormat('!Y-m', $month) ?: now();
                // Any overlap with the month.
                $q->where('from_date', '<=', $start->copy()->endOfMonth()->toDateString())->where('to_date', '>=', $start->toDateString());
            }),
            SelectFilter::make('type', __('Leave type'), LeaveType::query()->ordered()->pluck('name', 'id')->all())
                ->query(fn (Builder $q, string $id) => $q->where('leave_type_id', (int) $id)),
            SelectFilter::make('status', __('Status'), [
                'waiting' => __('Waiting for approval'), 'approved' => __('Approved'),
                'review' => __('Sent back'), 'rejected' => __('Rejected'), 'withdrawn' => __('Withdrawn'),
            ])->query(fn (Builder $q, string $v) => match ($v) {
                'approved' => $q->whereNotNull('approved_at')->whereNull('withdrawn_at'),
                'rejected' => $q->whereNotNull('rejected_at'),
                'withdrawn' => $q->whereNotNull('withdrawn_at'),
                'waiting' => $active($q)->whereHas('approvals', $pending),
                default => $active($q)->whereNull('approved_at')->whereDoesntHave('approvals', $pending),
            }),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('No.'), 'id')->sortable()->alwaysVisible()
                ->format(fn (LeaveApplication $a) => $a->number())->opens('show-leave'),
            Column::make(__('Employee'), 'employee_id')->alwaysVisible()
                ->format(fn (LeaveApplication $a) => $a->employee ? $a->employee->code.' · '.$a->employee->name : '—'),
            Column::make(__('Leave type'), 'leave_type_id')->format(fn (LeaveApplication $a) => $a->leaveType->name ?? '—'),
            Column::make(__('Dates'), 'from_date')->sortable()->format(fn (LeaveApplication $a) => $a->datesLabel()),
            Column::make(__('Days'), 'days')->sortable()->alignRight()->format(fn (LeaveApplication $a) => LeaveApplication::daysLabel($a->days)),
            Column::make(__('Reason'), 'reason')->searchable(),
            Column::make(__('Status'), 'approved_at')->format(function (LeaveApplication $a) {
                [$label, $color] = $a->statusBadge();

                return new HtmlString(Blade::render('<x-ui.badge :$color>{{ $label }}</x-ui.badge>', compact('label', 'color')));
            }),
            Column::make(__('Applied'), 'created_at')->sortable()->format(fn (LeaveApplication $a) => format_date($a->created_at)),
        ];
    }
}
