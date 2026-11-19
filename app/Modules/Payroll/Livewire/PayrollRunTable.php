<?php

namespace App\Modules\Payroll\Livewire;

use App\Core\Exports\ArrayExport;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Enums\PayrollRunType;
use App\Modules\Payroll\Models\PayrollRun;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Payroll → Payroll runs: the list, newest period first. Only runs the user may see (PayrollRun::visibleTo).
 * Filters: year, type, status. Click a code to edit the run's pay date / note (the run's own page is 10.8).
 *
 * View: app/Modules/Payroll/resources/views/livewire/payroll-run-table.blade.php
 */
class PayrollRunTable extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $year = '';

    #[Url(as: 'type', except: '')]
    public string $runType = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(as: 'sort', except: '')]
    public string $sortBy = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    #[Url(as: 'per_page', except: 10)]
    public int $perPage = 10;

    private const SORTABLE = ['code', 'period_start', 'employee_count', 'total_net', 'pay_date'];

    /** @var array<string, string> */
    protected $listeners = ['payroll-run-saved' => 'clearSelection'];

    /** @var list<string> ids of the ticked rows */
    public array $selected = [];

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function applyFilters(): void
    {
        $this->resetPage();
        $this->selected = [];
        Flux::modal('payroll-run-filters')->close();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'year', 'runType', 'status');
        $this->resetPage();
        $this->selected = [];
        Flux::modal('payroll-run-filters')->close();
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
        $rows = $this->filteredQuery()->get()->map(fn (PayrollRun $run) => [
            $run->code,
            $run->period()->label(),
            $run->run_type->label().($run->head ? ' ('.$run->head->name.')' : ''),
            $run->entity->name ?? __('Whole company'),
            $run->employee_count,
            $run->total_gross->toDecimal(),
            $run->total_deductions->toDecimal(),
            $run->total_net->toDecimal(),
            format_date($run->pay_date),
            $run->status->label(),
        ])->all();

        return Excel::download(
            new ArrayExport([__('Code'), __('Period'), __('Type'), __('Scope'), __('Employees'), __('Gross'), __('Deductions'), __('Net'), __('Pay date'), __('Status')], $rows),
            'payroll-runs-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** @return Builder<PayrollRun> */
    private function filteredQuery(): Builder
    {
        $query = PayrollRun::query()->visibleTo(Auth::user())->with(['entity:id,name', 'head:id,name']);

        if (trim($this->search) !== '') {
            $query->where('code', 'like', '%'.addcslashes(trim($this->search), '%_\\').'%');
        }
        if ($this->year !== '') {
            $query->where('year', (int) $this->year);
        }
        if (PayrollRunType::tryFrom($this->runType)) {
            $query->where('run_type', $this->runType);
        }
        if (PayrollRunStatus::tryFrom($this->status)) {
            $query->where('status', $this->status);
        }

        if (in_array($this->sortBy, self::SORTABLE, true)) {
            $query->orderBy($this->sortBy, $this->sortDir === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderByDesc('period_start')->orderByDesc('id');
        }

        return $query;
    }

    public function render(): View
    {
        $perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 10;
        $years = PayrollRun::query()->visibleTo(Auth::user())->distinct()->orderByDesc('year')->pluck('year')->all();

        return view('payroll::livewire.payroll-run-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
            'years' => $years,
        ]);
    }
}
