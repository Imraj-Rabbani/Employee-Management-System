<?php

namespace App\Modules\Hrm\Livewire\Leave;

use App\Core\Exports\ArrayExport;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveApplication;
use App\Modules\Hrm\Models\LeaveEncashment;
use App\Modules\Workflow\Enums\ApprovalStatus;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * HRM → Leave → Encashments: encashments of the employees the user can see, newest first.
 * Click the number of one that was sent back to change it; "Withdraw" until payroll paid it.
 *
 * View: app/Modules/Hrm/resources/views/livewire/leave/encashment-table.blade.php
 */
class EncashmentTable extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: '')]
    public string $sortBy = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    /** Filter "Status": '' = all */
    #[Url(as: 'status', except: '')]
    public string $status = '';

    #[Url(as: 'per_page', except: 10)]
    public int $perPage = 10;

    /** Columns the user may sort by. Anything else sent from the browser is ignored. */
    private const SORTABLE = ['id', 'days', 'created_at'];

    /** @var array<string, string> */
    protected $listeners = ['encashment-saved' => 'clearSelection'];

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
        Flux::modal('encashment-filters')->close();
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
        $this->reset('search', 'status');
        $this->resetPage();
        $this->selected = [];
        Flux::modal('encashment-filters')->close();
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (LeaveEncashment $leaveEncashment) => [
            $leaveEncashment->number(),
            $leaveEncashment->employee ? $leaveEncashment->employee->code.' · '.$leaveEncashment->employee->name : '—',
            $leaveEncashment->leaveType->name ?? '—',
            LeaveApplication::daysLabel($leaveEncashment->days),
            $leaveEncashment->amount ?? '—',
            $leaveEncashment->reason,
            $leaveEncashment->statusBadge()[0],
            format_date($leaveEncashment->created_at),
            '',
        ])->all();

        return Excel::download(
            new ArrayExport([__('No.'), __('Employee'), __('Leave type'), __('Days'), __('Amount'), __('Reason'), __('Status'), __('Asked'), ''], $rows),
            'encashment-table-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** Typing a search or picking a filter or page size starts again at page 1. */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /** @return Builder<LeaveEncashment> */
    private function query(): Builder
    {
        return LeaveEncashment::query()
            ->with(['employee:id,code,first_name,last_name', 'leaveType:id,code,name', 'approvals'])
            ->whereIn('employee_id', Employee::query()->select('id'));
    }

    /** @return array<int|string, string> choices of the "Status" filter */
    private function statusOptions(): array
    {
        return [
            'waiting' => __('Waiting for approval'), 'payroll' => __('Approved · waiting for payroll'), 'paid' => __('Paid'),
            'closed' => __('Rejected or withdrawn'),
        ];
    }

    /** @return Builder<LeaveEncashment> */
    private function filteredQuery(): Builder
    {
        $query = $this->query();

        if (trim($this->search) !== '') {
            // addcslashes: a search for "50%" means the text "50%", not "50 followed by anything".
            $like = '%'.addcslashes(trim($this->search), '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('reason', 'like', $like));
        }

        if ($this->status !== '' && array_key_exists($this->status, $this->statusOptions())) {
            match ($this->status) {
                'waiting' => ($query->whereIn('id', LeaveEncashment::query()->active()->select('id')))->whereHas('approvals', fn (Builder $a) => $a->where('status', ApprovalStatus::Pending)),
                'payroll' => ($query->whereIn('id', LeaveEncashment::query()->readyForPayroll()->select('id'))),
                'paid' => $query->whereNotNull('paid_at'),
                default => $query->where(fn ($query) => $query->whereNotNull('rejected_at')->orWhereNotNull('withdrawn_at')),
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

        return view('hrm::livewire.leave.encashment-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
            'statusOptions' => $this->statusOptions(),
        ]);
    }
}
