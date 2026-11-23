<?php

namespace App\Modules\Payroll\Livewire;

use App\Core\Exports\ArrayExport;
use App\Modules\Payroll\Actions\ProcessPayrollRun;
use App\Modules\Payroll\Enums\PayrollCalcStatus;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Enums\PayrollRunType;
use App\Modules\Payroll\Models\PayrollRun;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Payroll → Payroll runs: the list, newest period first. Only runs the user may see (PayrollRun::visibleTo).
 * Filters: year, type, status. Click a code for the run's own page (review: register, exceptions); Edit changes
 * its pay date / note.
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

    /**
     * Toolbar → Calculate: starts the calculation (a queued job per employee) of every ticked run that may be
     * calculated. One toast says what was started and what was not, and why. A single run opens its progress.
     */
    public function calculate(ProcessPayrollRun $action): void
    {
        Gate::authorize('payroll.run.process');
        $started = [];
        $problems = [];
        foreach (PayrollRun::query()->visibleTo(Auth::user())->whereKey($this->selected)->get() as $run) {
            if ($problem = $action->problem($run)) {
                $problems[] = $run->code.': '.$problem;

                continue;
            }
            $action->handle($run);
            $started[] = $run->id;
        }

        $this->selected = [];
        $this->dispatch('toast',
            message: implode(' ', array_filter([$started !== [] ? trans_choice('Calculation started for :count run.|Calculation started for :count runs.', count($started)) : null, ...$problems])),
            variant: $problems === [] ? 'success' : ($started !== [] ? 'warning' : 'error'),
        );
        if (count($started) === 1) {
            $this->dispatch('show-payroll-run', id: $started[0]);
        }
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
        // done_count: employees already calculated (ok or failed), for "12 / 40" next to a run that is processing.
        $query = PayrollRun::query()->visibleTo(Auth::user())->with(['entity:id,name', 'head:id,name'])
            ->withCount(['employees as done_count' => fn (Builder $q) => $q->where('calc_status', '!=', PayrollCalcStatus::Pending->value)]);

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

        $rows = $this->filteredQuery()->paginate($perPage);

        return view('payroll::livewire.payroll-run-table', [
            'rows' => $rows,
            'busy' => $rows->contains(fn (PayrollRun $run) => $run->status === PayrollRunStatus::Processing),
            'years' => $years,
        ]);
    }
}
