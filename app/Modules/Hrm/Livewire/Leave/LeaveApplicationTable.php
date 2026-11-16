<?php

namespace App\Modules\Hrm\Livewire\Leave;

use App\Core\Exports\ArrayExport;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveApplication;
use App\Modules\Hrm\Models\LeaveType;
use App\Modules\Workflow\Enums\ApprovalStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * HRM → Leave → Applications: every leave application of the employees the user can see, newest first.
 * Click the number to open it (details, timeline, change / withdraw).
 *
 * View: app/Modules/Hrm/resources/views/livewire/leave/leave-application-table.blade.php
 */
class LeaveApplicationTable extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: '')]
    public string $sortBy = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    /** Filter "Month": '' = all */
    #[Url(as: 'month', except: '')]
    public string $month = '';

    /** Filter "Leave type": '' = all */
    #[Url(as: 'type', except: '')]
    public string $type = '';

    /** Filter "Status": '' = all */
    #[Url(as: 'status', except: '')]
    public string $status = '';

    #[Url(as: 'per_page', except: 10)]
    public int $perPage = 10;

    /** Columns the user may sort by. Anything else sent from the browser is ignored. */
    private const SORTABLE = ['id', 'from_date', 'days', 'created_at'];

    /** @var array<string, string> */
    protected $listeners = ['leave-saved' => '$refresh'];

    public function sort(string $field): void
    {
        if (! in_array($field, self::SORTABLE, true)) {
            return;
        }

        // Clicking the same column again flips the direction.
        $this->sortDir = $this->sortBy === $field && $this->sortDir === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $field;
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'month', 'type', 'status');
        $this->resetPage();
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (LeaveApplication $leaveApplication) => [
            $leaveApplication->number(),
            $leaveApplication->employee ? $leaveApplication->employee->code.' · '.$leaveApplication->employee->name : '—',
            $leaveApplication->leaveType->name ?? '—',
            $leaveApplication->datesLabel(),
            LeaveApplication::daysLabel($leaveApplication->days),
            $leaveApplication->reason,
            $leaveApplication->statusBadge()[0],
            format_date($leaveApplication->created_at),
        ])->all();

        return Excel::download(
            new ArrayExport([__('No.'), __('Employee'), __('Leave type'), __('Dates'), __('Days'), __('Reason'), __('Status'), __('Applied')], $rows),
            'leave-application-table-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** Typing a search or picking a filter or page size starts again at page 1. */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'month', 'type', 'status', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /** @return Builder<LeaveApplication> */
    private function query(): Builder
    {
        // Employee::query() has the visibility scope (the relation itself does not: approvers need it).
        return LeaveApplication::query()
            ->with(['employee:id,code,first_name,last_name', 'leaveType:id,code,name', 'approvals'])
            ->whereIn('employee_id', Employee::query()->select('id'));
    }

    /** @return array<int|string, string> choices of the "Month" filter */
    private function monthOptions(): array
    {
        $months = [];

        for ($m = now()->addMonths(2)->startOfMonth(), $i = 0; $i < 15; $i++, $m = $m->subMonth()) {
            $months[$m->format('Y-m')] = $m->translatedFormat('F Y');
        }

        return $months;
    }

    /** @return array<int|string, string> choices of the "Leave type" filter */
    private function typeOptions(): array
    {
        return LeaveType::query()->ordered()->pluck('name', 'id')->all();
    }

    /** @return array<int|string, string> choices of the "Status" filter */
    private function statusOptions(): array
    {
        return [
            'waiting' => __('Waiting for approval'), 'approved' => __('Approved'),
            'review' => __('Sent back'), 'rejected' => __('Rejected'), 'withdrawn' => __('Withdrawn'),
        ];
    }

    /** @return Builder<LeaveApplication> */
    private function filteredQuery(): Builder
    {
        $query = $this->query();

        if (trim($this->search) !== '') {
            // addcslashes: a search for "50%" means the text "50%", not "50 followed by anything".
            $like = '%'.addcslashes(trim($this->search), '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('reason', 'like', $like));
        }

        if ($this->month !== '' && array_key_exists($this->month, $this->monthOptions())) {
            $start = Carbon::createFromFormat('!Y-m', $this->month) ?: now();

            $query->where('from_date', '<=', $start->copy()->endOfMonth()->toDateString())->where('to_date', '>=', $start->toDateString());
        }

        if ($this->type !== '' && array_key_exists($this->type, $this->typeOptions())) {
            $query->where('leave_type_id', (int) $this->type);
        }

        if ($this->status !== '' && array_key_exists($this->status, $this->statusOptions())) {
            match ($this->status) {
                'approved' => $query->whereNotNull('approved_at')->whereNull('withdrawn_at'),
                'rejected' => $query->whereNotNull('rejected_at'),
                'withdrawn' => $query->whereNotNull('withdrawn_at'),
                'waiting' => ($query->whereIn('id', LeaveApplication::query()->active()->select('id')))->whereHas('approvals', fn (Builder $a) => $a->where('status', ApprovalStatus::Pending)),
                default => ($query->whereIn('id', LeaveApplication::query()->active()->select('id')))->whereNull('approved_at')->whereDoesntHave('approvals', fn (Builder $a) => $a->where('status', ApprovalStatus::Pending)),
            };
        }

        if (in_array($this->sortBy, self::SORTABLE, true)) {
            $query->orderBy($this->sortBy, $this->sortDir === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query;
    }

    public function render(): View
    {
        // A page size typed into the URL that is not offered falls back to 10.
        $perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 10;

        return view('hrm::livewire.leave.leave-application-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
            'monthOptions' => $this->monthOptions(),
            'typeOptions' => $this->typeOptions(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }
}
