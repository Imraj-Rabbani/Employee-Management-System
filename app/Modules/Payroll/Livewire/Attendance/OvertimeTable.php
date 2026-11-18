<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Core\Exports\ArrayExport;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Models\OvertimeRequest;
use App\Modules\Workflow\Enums\ApprovalStatus;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Payroll → Attendance → Overtime: every overtime request of the employees the user can see, newest first.
 * Asked / recommended / approved hours side by side. Click the number of a request sent back to change it.
 *
 * View: app/Modules/Payroll/resources/views/livewire/attendance/overtime-table.blade.php
 */
class OvertimeTable extends Component
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

    /** Filter "Status": '' = all */
    #[Url(as: 'status', except: '')]
    public string $status = '';

    #[Url(as: 'per_page', except: 10)]
    public int $perPage = 10;

    /** Columns the user may sort by. Anything else sent from the browser is ignored. */
    private const SORTABLE = ['id', 'date', 'hours', 'approved_hours'];

    /** @var array<string, string> */
    protected $listeners = ['overtime-saved' => 'clearSelection'];

    /** @var list<string> ids of the ticked rows (the checkboxes). Edit needs one; Delete / Request approval any number. */
    public array $selected = [];

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    /** Filter form → Apply: the new filter values came with this click; start again at page 1. */
    public function applyFilters(): void
    {
        $this->resetPage();
        $this->selected = [];
        Flux::modal('overtime-filters')->close();
    }

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
        $this->reset('search', 'month', 'status');
        $this->resetPage();
        $this->selected = [];
        Flux::modal('overtime-filters')->close();
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (OvertimeRequest $overtimeRequest) => [
            $overtimeRequest->number(),
            format_date($overtimeRequest->date).' · '.$overtimeRequest->date->translatedFormat('D'),
            $overtimeRequest->employee ? $overtimeRequest->employee->code.' · '.$overtimeRequest->employee->name : '—',
            $overtimeRequest->shift->code ?? '—',
            OvertimeRequest::hoursLabel($overtimeRequest->hours),
            OvertimeRequest::hoursLabel($overtimeRequest->approved_at ? $overtimeRequest->recommended_hours : $overtimeRequest->recommendedHours()),
            OvertimeRequest::hoursLabel($overtimeRequest->approved_hours),
            $overtimeRequest->detail,
            $overtimeRequest->statusBadge()[0],
        ])->all();

        return Excel::download(
            new ArrayExport([__('No.'), __('Day'), __('Employee'), __('Shift'), __('Asked'), __('Recommended'), __('Approved'), __('Work done'), __('Status')], $rows),
            'overtime-table-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** Typing a search or picking a filter or page size starts again at page 1. */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'month', 'status', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /** @return Builder<OvertimeRequest> */
    private function query(): Builder
    {
        // Employee::query() has the visibility scope (the relation itself does not: approvers need it).
        return OvertimeRequest::query()
            ->with(['employee:id,code,first_name,last_name', 'shift:id,code', 'approvals'])
            ->whereIn('employee_id', Employee::query()->select('id'));
    }

    /** @return array<int|string, string> choices of the "Month" filter */
    private function monthOptions(): array
    {
        $months = [];

        for ($m = now()->startOfMonth(), $i = 0; $i < 13; $i++, $m = $m->subMonth()) {
            $months[$m->format('Y-m')] = $m->translatedFormat('F Y');
        }

        return $months;
    }

    /** @return array<int|string, string> choices of the "Status" filter */
    private function statusOptions(): array
    {
        return ['waiting' => __('Waiting for approval'), 'approved' => __('Approved'), 'other' => __('Sent back or rejected')];
    }

    /** @return Builder<OvertimeRequest> */
    private function filteredQuery(): Builder
    {
        $query = $this->query();

        if (trim($this->search) !== '') {
            // addcslashes: a search for "50%" means the text "50%", not "50 followed by anything".
            $like = '%'.addcslashes(trim($this->search), '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('detail', 'like', $like));
        }

        if ($this->month !== '' && array_key_exists($this->month, $this->monthOptions())) {
            $start = Carbon::createFromFormat('!Y-m', $this->month) ?: now();
            $query->whereBetween('date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()]);
        }

        if ($this->status !== '' && array_key_exists($this->status, $this->statusOptions())) {
            match ($this->status) {
                'approved' => $query->whereNotNull('approved_at'),
                'waiting' => $query->whereHas('approvals', fn (Builder $a) => $a->where('status', ApprovalStatus::Pending)),
                default => $query->whereNull('approved_at')->whereDoesntHave('approvals', fn (Builder $a) => $a->where('status', ApprovalStatus::Pending)),
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

        return view('payroll::livewire.attendance.overtime-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
            'monthOptions' => $this->monthOptions(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }
}
