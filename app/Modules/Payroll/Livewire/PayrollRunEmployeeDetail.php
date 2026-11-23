<?php

namespace App\Modules\Payroll\Livewire;

use App\Core\Support\Money;
use App\Modules\Payroll\Actions\RecalculatePayrollEmployees;
use App\Modules\Payroll\Enums\HeadType;
use App\Modules\Payroll\Models\PayrollAdjustment;
use App\Modules\Payroll\Models\PayrollArrear;
use App\Modules\Payroll\Models\PayrollItem;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\PayrollRunEmployee;
use App\Modules\Payroll\Services\PayrollReview;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * One employee of a run in full (modal "payroll-run-employee"): days, every item (where it came from, the full-period
 * amount, back pay included) next to the previous period, the arrears ledger, the adjustments paid, warnings / error.
 * Event "show-payroll-employee" (id = payroll_run_employees.id).
 *
 * View: app/Modules/Payroll/resources/views/livewire/payroll-run-employee-detail.blade.php
 */
class PayrollRunEmployeeDetail extends Component
{
    #[Locked]
    public ?int $rowId = null;

    #[On('show-payroll-employee')]
    public function show(int $id): void
    {
        Gate::authorize('payroll.run.view');
        $this->rowId = $this->find($id)->id;

        Flux::modal('payroll-run-employee')->show();
    }

    /** "Calculate again" for this one employee (draft / processed run). */
    public function recalculate(RecalculatePayrollEmployees $action): void
    {
        Gate::authorize('payroll.run.process');
        $row = $this->find((int) $this->rowId);
        if ($problem = $action->problem($row->run, [$row->id])) {
            $this->dispatch('toast', variant: 'error', message: $problem);

            return;
        }

        $action->handle($row->run, [$row->id]);
        $this->dispatch('payroll-run-refreshed');
        $this->dispatch('toast', variant: 'success', message: __(':name calculated again.', ['name' => $row->employee()->withoutGlobalScopes()->value('first_name') ?? '']));
    }

    private function find(int $id): PayrollRunEmployee
    {
        return PayrollRunEmployee::query()
            ->whereIn('payroll_run_id', PayrollRun::query()->visibleTo(Auth::user())->select('id'))
            ->findOrFail($id);
    }

    public function render(PayrollReview $review): View
    {
        $row = $this->rowId ? $this->find($this->rowId)->load([
            'run', 'entity', 'bankAccount',
            'employee' => fn ($q) => $q->withoutGlobalScopes(),
            'items.head',
        ]) : null;

        $groups = [];
        $before = [];
        $previous = null;
        if ($row !== null) {
            $previous = $review->previousRows($row->run)[$row->employee_id] ?? null;
            if ($previous !== null) {
                foreach (PayrollItem::query()->where('payroll_run_employee_id', $previous->id)->get() as $item) {
                    $before[$item->salary_head_id] = ($before[$item->salary_head_id] ?? Money::zero())->add($item->amount);
                }
            }
            foreach ([HeadType::Earning, HeadType::Deduction, HeadType::Info] as $type) {
                $items = $row->items->filter(fn (PayrollItem $i) => $i->head->type === $type)
                    ->sortBy(fn (PayrollItem $i) => [$i->head->sequence, $i->head->name])->values();
                if ($items->isNotEmpty()) {
                    $groups[] = [
                        'label' => match ($type) { HeadType::Earning => __('Earnings'), HeadType::Deduction => __('Deductions'), default => __('Information (not paid)') },
                        'items' => $items,
                        'total' => $items->reduce(fn (Money $sum, PayrollItem $i) => $sum->add($i->amount), Money::zero()),
                    ];
                }
            }
        }

        return view('payroll::livewire.payroll-run-employee-detail', [
            'row' => $row,
            'canRecalculate' => $row !== null && $row->run->status->isEditable() && Gate::allows('payroll.run.process'),
            'groups' => $groups,
            'before' => $before,
            'previous' => $previous,
            'change' => $row ? PayrollReview::change($row->net, $previous?->net) : null,
            'arrears' => $row ? PayrollArrear::query()->where('payroll_run_employee_id', $row->id)->with(['head:id,name,code', 'forRun:id,code'])->orderBy('for_period_start')->get() : collect(),
            'adjustments' => $row ? PayrollAdjustment::query()->where('payroll_run_id', $row->payroll_run_id)->where('employee_id', $row->employee_id)->with('head:id,name,type')->get() : collect(),
        ]);
    }
}
