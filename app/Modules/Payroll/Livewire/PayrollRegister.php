<?php

namespace App\Modules\Payroll\Livewire;

use App\Core\Exports\ArrayExport;
use App\Core\Support\Money;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Payroll\Actions\RecalculatePayrollEmployees;
use App\Modules\Payroll\Enums\PayrollCalcStatus;
use App\Modules\Payroll\Models\PayrollItem;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\PayrollRunEmployee;
use App\Modules\Payroll\Models\SalaryHead;
use App\Modules\Payroll\Services\PayrollReview;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The payroll register of one run (PR-04): one row per employee, days, one column per head paid in the run
 * (earnings, deductions, information), gross / deductions / net, and the net of the previous period with the change.
 * Totals of the filtered rows at the bottom. Click an employee for the full breakdown (PayrollRunEmployeeDetail).
 * Tick employees → "Calculate again" (RecalculatePayrollEmployees, at once) while the run is draft / processed.
 *
 * View: app/Modules/Payroll/resources/views/livewire/payroll-register.blade.php
 */
class PayrollRegister extends Component
{
    use WithPagination;

    #[Locked]
    public int $runId;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'entity', except: '')]
    public string $entity = '';

    /** '' = all, ok, failed, pending, warnings, variance, new */
    #[Url(as: 'show', except: '')]
    public string $status = '';

    #[Url(as: 'sort', except: '')]
    public string $sortBy = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    #[Url(as: 'per_page', except: 25)]
    public int $perPage = 25;

    private const SORTABLE = ['code', 'payable_days', 'gross', 'deductions', 'net'];

    /** @var list<string> ids (payroll_run_employees) of the ticked rows */
    public array $selected = [];

    public function mount(int $runId): void
    {
        $this->runId = $runId;
    }

    #[On('payroll-run-refreshed')]
    public function refreshed(): void
    {
        // The calculation ended: render again with the new numbers.
    }

    /** Toolbar → Calculate again: the ticked employees, right away. */
    public function recalculate(RecalculatePayrollEmployees $action): void
    {
        Gate::authorize('payroll.run.process');
        $run = $this->run();
        $ids = array_map('intval', $this->selected);
        if ($problem = $action->problem($run, $ids)) {
            $this->dispatch('toast', variant: 'error', message: $problem);

            return;
        }

        $ok = $action->handle($run, $ids);
        $failed = count($ids) - $ok;
        $this->selected = [];
        $this->dispatch('payroll-run-refreshed');
        $this->dispatch('toast', variant: $failed > 0 ? 'warning' : 'success', message: trans_choice(':count employee calculated again.|:count employees calculated again.', count($ids))
            .($failed > 0 ? ' '.trans_choice(':count failed: see the Exceptions tab.|:count failed: see the Exceptions tab.', $failed) : ''));
    }

    public function applyFilters(): void
    {
        $this->resetPage();
        Flux::modal('register-filters')->close();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'entity', 'status');
        $this->resetPage();
        Flux::modal('register-filters')->close();
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

    public function export(PayrollReview $review): BinaryFileResponse
    {
        $run = $this->run();
        $heads = $review->heads($run);
        $previous = $review->previousRows($run);

        $lines = [];
        foreach ($this->sorted($this->filtered($run, $previous))->get()->chunk(500) as $chunk) {
            $cells = $this->cells($chunk);
            foreach ($chunk as $row) {
                $change = PayrollReview::change($row->net, $previous[$row->employee_id]->net ?? null);
                $lines[] = [
                    $row->employee->code ?? '', $row->employee->name ?? '', $row->entity->name ?? '',
                    $row->period_days, $row->payable_days, $row->lwp_days, $row->absent_days, $row->overtime_hours,
                    ...$heads->map(fn (SalaryHead $h) => isset($cells[$row->id][$h->id]) ? $cells[$row->id][$h->id]->toDecimal() : '')->all(),
                    $row->gross->toDecimal(), $row->deductions->toDecimal(), $row->net->toDecimal(),
                    isset($previous[$row->employee_id]) ? $previous[$row->employee_id]->net->toDecimal() : '',
                    $change['diff']->toDecimal(), PayrollReview::percentLabel($change['percent']),
                    $row->calc_status->label(), implode(' | ', $row->error ? [$row->error] : ($row->warnings ?? [])),
                ];
            }
        }

        return Excel::download(new ArrayExport([
            __('Code'), __('Name'), __('Entity'), __('Period days'), __('Payable days'), __('LWP days'), __('Absent days'), __('Overtime hours'),
            ...$heads->map(fn (SalaryHead $h) => $h->code)->all(),
            __('Gross'), __('Deductions'), __('Net'), __('Previous net'), __('Change'), __('Change %'), __('Status'), __('Notes'),
        ], $lines), 'payroll-register-'.$run->code.'.xlsx');
    }

    private function run(): PayrollRun
    {
        return PayrollRun::query()->visibleTo(Auth::user())->findOrFail($this->runId);
    }

    /**
     * @param  Collection<int, PayrollRunEmployee>  $previous
     * @return Builder<PayrollRunEmployee>
     */
    private function filtered(PayrollRun $run, Collection $previous): Builder
    {
        $query = PayrollRunEmployee::query()->where('payroll_run_id', $run->id);

        if (trim($this->search) !== '') {
            $like = '%'.addcslashes(trim($this->search), '%_\\').'%';
            $query->whereIn('employee_id', Employee::withoutGlobalScopes()->select('id')
                ->where(fn (Builder $q) => $q->where('code', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like)));
        }
        if (ctype_digit($this->entity)) {
            $query->where('entity_id', (int) $this->entity);
        }

        match ($this->status) {
            'ok', 'failed', 'pending' => $query->where('calc_status', $this->status),
            'warnings' => $query->where('calc_status', PayrollCalcStatus::Ok->value)->whereNotNull('warnings'),
            'variance' => $query->whereIn('id', $this->varianceIds($run, $previous)),
            'new' => $query->where('calc_status', '!=', PayrollCalcStatus::Pending->value)->whereNotIn('employee_id', $previous->keys()->all()),
            default => null,
        };

        return $query;
    }

    /**
     * @param  Builder<PayrollRunEmployee>  $query
     * @return Builder<PayrollRunEmployee>
     */
    private function sorted(Builder $query): Builder
    {
        $dir = $this->sortDir === 'desc' ? 'desc' : 'asc';
        $field = in_array($this->sortBy, self::SORTABLE, true) ? $this->sortBy : 'code';
        if ($field === 'code') {
            $query->orderBy(Employee::withoutGlobalScopes()->select('code')->whereColumn('employees.id', 'payroll_run_employees.employee_id'), $dir);
        } else {
            $query->orderBy($field, $dir);
        }

        return $query->orderBy('id')->with([
            'employee' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'code', 'first_name', 'last_name'),
            'entity',
        ]);
    }

    /**
     * @param  Collection<int, PayrollRunEmployee>  $previous
     * @return list<int>
     */
    private function varianceIds(PayrollRun $run, Collection $previous): array
    {
        return PayrollRunEmployee::query()->where('payroll_run_id', $run->id)->where('calc_status', PayrollCalcStatus::Ok->value)
            ->get(['id', 'employee_id', 'net'])
            ->filter(fn (PayrollRunEmployee $row) => isset($previous[$row->employee_id])
                && PayrollReview::isVariance(PayrollReview::change($row->net, $previous[$row->employee_id]->net)['percent']))
            ->pluck('id')->all();
    }

    /**
     * @param  iterable<PayrollRunEmployee>  $rows
     * @return array<int, array<int, Money>> row id => head id => amount
     */
    private function cells(iterable $rows): array
    {
        $ids = collect($rows)->pluck('id')->all();
        $cells = [];
        $sums = PayrollItem::query()->whereIn('payroll_run_employee_id', $ids)
            ->selectRaw('payroll_run_employee_id, salary_head_id, SUM(amount) AS total')
            ->groupBy('payroll_run_employee_id', 'salary_head_id')->get();
        foreach ($sums as $sum) {
            $cells[$sum->payroll_run_employee_id][$sum->salary_head_id] = Money::of((string) $sum->getAttribute('total'));
        }

        return $cells;
    }

    public function render(PayrollReview $review): View
    {
        $run = $this->run();
        $perPage = in_array($this->perPage, [25, 50, 100, 250], true) ? $this->perPage : 25;
        $previous = $review->previousRows($run);
        $filtered = $this->filtered($run, $previous);

        $rows = $this->sorted($filtered->clone())->paginate($perPage);

        // Totals of ALL filtered rows, not just this page.
        $headTotals = PayrollItem::query()->whereIn('payroll_run_employee_id', $filtered->clone()->select('id'))
            ->selectRaw('salary_head_id, SUM(amount) AS total')->groupBy('salary_head_id')
            ->pluck('total', 'salary_head_id')->map(fn ($t) => Money::of((string) $t));
        $totals = $filtered->clone()->selectRaw('COUNT(*) AS n, SUM(gross) AS gross, SUM(deductions) AS deductions, SUM(net) AS net')->toBase()->first();

        return view('payroll::livewire.payroll-register', [
            'run' => $run,
            'canRecalculate' => $run->status->isEditable() && Gate::allows('payroll.run.process'),
            'rows' => $rows,
            'heads' => $review->heads($run),
            'cells' => $this->cells($rows->items()),
            'previous' => $previous,
            'headTotals' => $headTotals,
            'totals' => [
                'count' => (int) ($totals->n ?? 0),
                'gross' => Money::of((string) ($totals->gross ?? '0')),
                'deductions' => Money::of((string) ($totals->deductions ?? '0')),
                'net' => Money::of((string) ($totals->net ?? '0')),
            ],
            'entities' => Entity::query()->whereIn('id', PayrollRunEmployee::query()->where('payroll_run_id', $run->id)->select('entity_id'))->orderBy('name')->get()
                ->mapWithKeys(fn (Entity $e) => [$e->id => $e->name])->all(),
            'statusOptions' => [
                'ok' => __('Calculated'), 'failed' => __('Failed'), 'pending' => __('Not calculated yet'), 'warnings' => __('With warnings'),
                'variance' => __('Net changed by :p % or more', ['p' => PayrollReview::VARIANCE_PERCENT]), 'new' => __('Not in the previous period'),
            ],
        ]);
    }
}
