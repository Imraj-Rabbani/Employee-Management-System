<?php

namespace App\Modules\Payroll\Livewire;

use App\Core\Exports\ArrayExport;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Models\PayrollAdjustment;
use App\Modules\Payroll\Models\SalaryHead;
use App\Modules\Workflow\Enums\ApprovalStatus;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Payroll → Adjustments: one-time earnings / deductions of the employees the user can see, newest first.
 * Click the number of one that was sent back to change it; "Withdraw" until a run took it for good.
 *
 * View: app/Modules/Payroll/resources/views/livewire/adjustment-table.blade.php
 */
class AdjustmentTable extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'year', except: '')]
    public string $year = '';

    #[Url(as: 'month', except: '')]
    public string $month = '';

    #[Url(as: 'head', except: '')]
    public string $head = '';

    /** '' = all, waiting, approved, taken, closed */
    #[Url(as: 'status', except: '')]
    public string $status = '';

    #[Url(as: 'sort', except: '')]
    public string $sortBy = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    #[Url(as: 'per_page', except: 10)]
    public int $perPage = 10;

    private const SORTABLE = ['id', 'amount', 'created_at'];

    /** @var list<string> ids of the ticked rows */
    public array $selected = [];

    #[On('adjustment-saved')]
    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function applyFilters(): void
    {
        $this->resetPage();
        $this->selected = [];
        Flux::modal('adjustment-filters')->close();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'year', 'month', 'head', 'status');
        $this->resetPage();
        $this->selected = [];
        Flux::modal('adjustment-filters')->close();
    }

    public function sort(string $field): void
    {
        if (! in_array($field, self::SORTABLE, true)) {
            return;
        }
        $this->sortDir = $this->sortBy === $field && $this->sortDir === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $field;
    }

    public function updated(string $property): void
    {
        if ($property === 'perPage') {
            $this->resetPage();
        }
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (PayrollAdjustment $a) => [
            $a->number(),
            $a->employee ? $a->employee->code.' · '.$a->employee->name : '—',
            $a->period()->label(),
            $a->head->name ?? '—',
            $a->head?->type->label() ?? '—',
            $a->amount->toDecimal(),
            $a->reason,
            $a->statusBadge()[0],
            format_date($a->created_at),
        ])->all();

        return Excel::download(
            new ArrayExport([__('No.'), __('Employee'), __('Month'), __('Head'), __('Type'), __('Amount'), __('Reason'), __('Status'), __('Asked')], $rows),
            'payroll-adjustments-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** @return array<string, string> */
    private function statusOptions(): array
    {
        return [
            'waiting' => __('Waiting for approval'), 'approved' => __('Approved · waiting for payroll'),
            'taken' => __('In a payroll run'), 'closed' => __('Rejected or withdrawn'),
        ];
    }

    /** @return Builder<PayrollAdjustment> */
    private function filteredQuery(): Builder
    {
        $query = PayrollAdjustment::query()
            ->with(['employee:id,code,first_name,last_name', 'head:id,code,name,type', 'run:id,code,status', 'approvals'])
            ->whereIn('employee_id', Employee::query()->select('id'));   // only employees the user can see

        if (trim($this->search) !== '') {
            $like = '%'.addcslashes(trim($this->search), '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('reason', 'like', $like)
                ->orWhereHas('employee', fn (Builder $e) => $e->where('code', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like)));
        }
        if (ctype_digit($this->year)) {
            $query->where('year', (int) $this->year);
        }
        if (ctype_digit($this->month)) {
            $query->where('month', (int) $this->month);
        }
        if (ctype_digit($this->head)) {
            $query->where('salary_head_id', (int) $this->head);
        }
        match ($this->status) {
            'waiting' => $query->active()->whereNull('approved_at')->whereHas('approvals', fn (Builder $a) => $a->where('status', ApprovalStatus::Pending)),
            'approved' => $query->active()->whereNotNull('approved_at')->whereNull('payroll_run_id'),
            'taken' => $query->active()->whereNotNull('payroll_run_id'),
            'closed' => $query->where(fn (Builder $q) => $q->whereNotNull('rejected_at')->orWhereNotNull('withdrawn_at')),
            default => null,
        };

        if (in_array($this->sortBy, self::SORTABLE, true)) {
            $query->orderBy($this->sortBy, $this->sortDir === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query;
    }

    public function render(): View
    {
        $perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 10;

        return view('payroll::livewire.adjustment-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
            'statusOptions' => $this->statusOptions(),
            'heads' => SalaryHead::query()->whereIn('id', PayrollAdjustment::query()->select('salary_head_id'))->ordered()->pluck('name', 'id')->all(),
            'years' => PayrollAdjustment::query()->distinct()->orderByDesc('year')->pluck('year')->all(),
        ]);
    }
}
