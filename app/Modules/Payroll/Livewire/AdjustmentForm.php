<?php

namespace App\Modules\Payroll\Livewire;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Actions\RequestAdjustment;
use App\Modules\Payroll\Enums\HeadType;
use App\Modules\Payroll\Models\PayrollAdjustment;
use App\Modules\Payroll\Models\SalaryHead;
use App\Modules\Workflow\Exceptions\WorkflowException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * "New adjustment" (modal "adjustment-form"), new or changed after a send-back, and "Withdraw".
 * Employees the user can see; payroll.adjustment.manage.
 *
 * Events: "create-adjustment", "edit-adjustment" (id), "withdraw-adjustment" (id). Sends "adjustment-saved".
 */
class AdjustmentForm extends Component
{
    #[Locked]
    public ?int $adjustmentId = null;

    public string $employeeId = '';

    public string $headId = '';

    public string $year = '';

    public string $month = '';

    public string $amount = '';

    public string $reason = '';

    #[On('create-adjustment')]
    public function create(): void
    {
        Gate::authorize('payroll.adjustment.manage');
        $this->reset('adjustmentId', 'employeeId', 'headId', 'amount', 'reason');
        $this->year = now()->format('Y');
        $this->month = (string) now()->month;
        $this->resetValidation();

        Flux::modal('adjustment-form')->show();
    }

    #[On('edit-adjustment')]
    public function edit(int $id): void
    {
        Gate::authorize('payroll.adjustment.manage');
        $adjustment = $this->find($id);
        if (! $adjustment->isEditable()) {
            $this->dispatch('toast', variant: 'error', message: __('Only an adjustment that was sent back can be changed.'));

            return;
        }

        $this->adjustmentId = $adjustment->id;
        $this->employeeId = (string) $adjustment->employee_id;
        $this->headId = (string) $adjustment->salary_head_id;
        $this->year = (string) $adjustment->year;
        $this->month = (string) $adjustment->month;
        $this->amount = $adjustment->amount->toDecimal();
        $this->reason = $adjustment->reason;
        $this->resetValidation();

        Flux::modal('adjustment-form')->show();
    }

    public function save(RequestAdjustment $action): void
    {
        Gate::authorize('payroll.adjustment.manage');
        $this->validate([
            'employeeId' => ['required', 'integer'],
            'headId' => ['required', 'integer'],
            'year' => ['required', 'integer'],
            'month' => ['required', 'integer'],
            'amount' => ['required', 'string'],
            'reason' => ['required', 'string', 'max:500'],
        ], attributes: ['employeeId' => __('employee'), 'headId' => __('head')]);

        $employee = Employee::query()->find((int) $this->employeeId);   // only one the user can see
        $head = SalaryHead::find((int) $this->headId);
        if ($employee === null || $head === null) {
            $this->addError($employee === null ? 'employeeId' : 'headId', __('Choose from the list.'));

            return;
        }

        $existing = $this->adjustmentId ? $this->find($this->adjustmentId) : null;
        $values = ['year' => $this->year, 'month' => $this->month, 'amount' => $this->amount, 'reason' => $this->reason];
        if ($problem = $action->problem($employee, $head, $values, $existing)) {
            $field = ['salary_head_id' => 'headId', 'year' => 'month'][$problem['field']] ?? $problem['field'];
            $this->addError($field, $problem['message']);

            return;
        }

        try {
            $adjustment = $action->handle($employee, $head, $values, $existing);
        } catch (WorkflowException $e) {
            $this->addError('reason', $e->getMessage());

            return;
        }

        Flux::modal('adjustment-form')->close();
        $this->dispatch('adjustment-saved');
        $this->dispatch('toast', variant: 'success', message: __(':no sent for approval.', ['no' => $adjustment->number()]));
    }

    #[On('withdraw-adjustment')]
    public function withdraw(int $id, RequestAdjustment $action): void
    {
        Gate::authorize('payroll.adjustment.manage');
        $adjustment = $this->find($id);

        if ($problem = $action->withdrawProblem($adjustment)) {
            $this->dispatch('toast', variant: 'error', message: $problem);

            return;
        }

        $run = $adjustment->run;
        $action->withdraw($adjustment);
        $this->dispatch('adjustment-saved');
        $this->dispatch('toast', variant: 'success', message: $run
            ? __(':no withdrawn. Calculate :run again to take it out.', ['no' => $adjustment->number(), 'run' => $run->code])
            : __(':no withdrawn.', ['no' => $adjustment->number()]));
    }

    private function find(int $id): PayrollAdjustment
    {
        return PayrollAdjustment::query()->whereIn('employee_id', Employee::query()->select('id'))->findOrFail($id);
    }

    public function render(): View
    {
        $head = $this->headId !== '' ? SalaryHead::find((int) $this->headId) : null;

        return view('payroll::livewire.adjustment-form', [
            'employees' => Employee::query()->active()->orderBy('code')->get(['id', 'code', 'first_name', 'last_name'])
                ->mapWithKeys(fn (Employee $e) => [$e->id => $e->code.' · '.$e->name])->all(),
            'heads' => SalaryHead::query()->where('is_active', true)->where('is_basic', false)
                ->whereIn('type', [HeadType::Earning, HeadType::Deduction])->ordered()->get()
                ->mapWithKeys(fn (SalaryHead $h) => [$h->id => "{$h->name} ({$h->code}) · {$h->type->label()}"])->all(),
            'isDeduction' => $head?->type === HeadType::Deduction,
        ]);
    }
}
