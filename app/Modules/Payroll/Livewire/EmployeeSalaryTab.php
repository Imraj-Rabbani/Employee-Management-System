<?php

namespace App\Modules\Payroll\Livewire;

use App\Core\Support\Money;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Actions\SetEmployeeSalary;
use App\Modules\Payroll\Data\SalaryBreakdown;
use App\Modules\Payroll\Data\SalaryLine;
use App\Modules\Payroll\Enums\CalcMethod;
use App\Modules\Payroll\Enums\ProfileValueType;
use App\Modules\Payroll\Enums\SalaryBasis;
use App\Modules\Payroll\Exceptions\SalaryProfileException;
use App\Modules\Payroll\Models\EmployeeSalary;
use App\Modules\Payroll\Models\PayScaleProfile;
use App\Modules\Payroll\Models\SalaryHead;
use App\Modules\Payroll\Models\SalaryProfile;
use App\Modules\Payroll\Services\PayrollSettings;
use App\Modules\Payroll\Services\SalaryProfileCalculator;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Profile → Salary: the pay scale profile (9.5), the current salary (every head + gross / deductions / net), the hold flags, and the history of
 * salaries (click one for its heads). [PC-03]
 *
 * Seeing it: EmployeePolicy::view + payroll.salary.view. Changing it: payroll.salary.manage.
 * "Change salary" modal: pick a profile + basic or gross → "Apply" fills every head (SalaryProfileCalculator);
 * any amount can then be changed by hand. Saved through SetEmployeeSalary (effective-dated).
 */
class EmployeeSalaryTab extends Component
{
    #[Locked]
    public int $employeeId;

    /** effective_from of the history row that is open ('Y-m-d'). */
    public ?string $openFrom = null;

    /** @var array{effective_from: string, salary_profile_id: string, basis: string, amount: string} */
    public array $form = ['effective_from' => '', 'salary_profile_id' => '', 'basis' => 'gross', 'amount' => ''];

    /** @var array<int, string> salary_head_id => amount as typed */
    public array $lines = [];

    /** @var array<int, array{value_type: string, rate: string}> how an amount was worked out; dropped when it is typed over */
    public array $workedOut = [];

    /** @var array{salary_on_hold: bool, bank_transfer_on_hold: bool, salary_hold_reason: string} */
    public array $hold = ['salary_on_hold' => false, 'bank_transfer_on_hold' => false, 'salary_hold_reason' => ''];

    /** pay_scale_profile_id being chosen ('' = the company default) */
    public string $payScaleId = '';

    public function mount(Employee $employee): void
    {
        $this->employeeId = $employee->id;
    }

    public function toggle(string $from): void
    {
        $this->openFrom = $this->openFrom === $from ? null : $from;
    }

    // ---- Change salary ------------------------------------------------------------------------

    public function openSalary(): void
    {
        $employee = $this->authorizeManage();

        $latest = EmployeeSalary::where('employee_id', $employee->id)->whereNull('effective_to')->with('head')->get();
        $profileId = $employee->salary_profile_id
            ?? SalaryProfile::where('is_active', true)->where('grade_id', $employee->grade_id)->orderBy('name')->value('id');

        $this->form = [
            'effective_from' => $latest->isEmpty() ? ($employee->join_date ?? today())->toDateString() : today()->toDateString(),
            'salary_profile_id' => (string) ($profileId ?? ''),
            'basis' => SalaryBasis::Gross->value,
            'amount' => $latest->isEmpty() ? '' : EmployeeSalary::breakdown($latest)->gross->toDecimal(),
        ];
        $this->lines = $latest->mapWithKeys(fn (EmployeeSalary $r) => [$r->salary_head_id => $r->amount->toDecimal()])->all();
        $this->workedOut = $latest->filter(fn (EmployeeSalary $r) => $r->value_type !== null)
            ->mapWithKeys(fn (EmployeeSalary $r) => [$r->salary_head_id => ['value_type' => $r->value_type->value, 'rate' => (string) $r->rate]])->all();
        $this->resetValidation();

        Flux::modal('employee-salary')->show();
    }

    /** Fills every head from the chosen profile and basic / gross. */
    public function applyProfile(SalaryProfileCalculator $calculator): void
    {
        $this->authorizeManage();
        $this->validate([
            'form.salary_profile_id' => ['required', Rule::exists(SalaryProfile::class, 'id')],
            'form.basis' => ['required', Rule::enum(SalaryBasis::class)],
            'form.amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
        ], attributes: ['form.salary_profile_id' => __('salary profile'), 'form.amount' => __('amount')]);

        try {
            $salary = $calculator->forProfile(SalaryProfile::findOrFail((int) $this->form['salary_profile_id']),
                SalaryBasis::from($this->form['basis']), Money::of($this->form['amount']));
        } catch (SalaryProfileException $e) {
            $this->addError('form.amount', $e->getMessage());

            return;
        }

        $this->lines = [];
        $this->workedOut = [];
        foreach ($salary->lines as $line) {
            $this->lines[$line->head->id] = $line->amount->toDecimal();
            if ($line->valueType !== null) {
                $this->workedOut[$line->head->id] = ['value_type' => $line->valueType->value, 'rate' => (string) $line->rate];
            }
        }
    }

    /** An amount typed by hand is a plain amount now, not "50% of basic". */
    public function updatedLines(mixed $value, string $key): void
    {
        unset($this->workedOut[(int) $key]);
    }

    public function saveSalary(SetEmployeeSalary $action): void
    {
        $employee = $this->authorizeManage();
        $this->validate([
            'form.effective_from' => ['required', 'date'],
            'form.salary_profile_id' => ['nullable', Rule::exists(SalaryProfile::class, 'id')],
            'lines' => ['array'],
            'lines.*' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
        ], attributes: ['form.effective_from' => __('effective from'), 'lines.*' => __('amount')]);

        $from = CarbonImmutable::parse($this->form['effective_from']);
        $salary = $this->typedSalary();

        if ($problem = $action->problem($employee, $from, $salary)) {
            $this->addError($problem['field'] === 'lines' ? 'lines' : 'form.'.$problem['field'], $problem['message']);

            return;
        }

        $profile = $this->form['salary_profile_id'] !== '' ? SalaryProfile::find((int) $this->form['salary_profile_id']) : null;
        $action->handle($employee, $from, $salary, $profile);

        Flux::modal('employee-salary')->close();
        $this->openFrom = null;
        $this->dispatch('toast', message: __('Salary saved.'), variant: 'success');
    }

    // ---- Hold ----------------------------------------------------------------------------------

    public function openHold(): void
    {
        $employee = $this->authorizeManage();
        $this->hold = [
            'salary_on_hold' => (bool) $employee->salary_on_hold,
            'bank_transfer_on_hold' => (bool) $employee->bank_transfer_on_hold,
            'salary_hold_reason' => (string) $employee->salary_hold_reason,
        ];
        $this->resetValidation();

        Flux::modal('salary-hold')->show();
    }

    public function saveHold(): void
    {
        $employee = $this->authorizeManage();
        $holding = $this->hold['salary_on_hold'] || $this->hold['bank_transfer_on_hold'];
        $this->validate([
            'hold.salary_on_hold' => ['boolean'],
            'hold.bank_transfer_on_hold' => ['boolean'],
            'hold.salary_hold_reason' => [$holding ? 'required' : 'nullable', 'string', 'max:500'],
        ], attributes: ['hold.salary_hold_reason' => __('reason')]);

        $employee->update([
            'salary_on_hold' => $this->hold['salary_on_hold'],
            'bank_transfer_on_hold' => $this->hold['bank_transfer_on_hold'],
            'salary_hold_reason' => $holding ? $this->hold['salary_hold_reason'] : null,   // the reason goes with the hold
        ]);

        Flux::modal('salary-hold')->close();
        $this->dispatch('toast', message: __('Salary hold saved.'), variant: 'success');
    }

    // ---- Pay scale profile ---------------------------------------------------------------------

    public function openPayScale(): void
    {
        $employee = $this->authorizeManage();
        $this->payScaleId = (string) ($employee->pay_scale_profile_id ?? '');
        $this->resetValidation();

        Flux::modal('pay-scale')->show();
    }

    public function savePayScale(): void
    {
        $employee = $this->authorizeManage();
        $this->validate(['payScaleId' => ['nullable', Rule::exists(PayScaleProfile::class, 'id')]], attributes: ['payScaleId' => __('pay scale profile')]);

        $employee->update(['pay_scale_profile_id' => $this->payScaleId !== '' ? (int) $this->payScaleId : null]);

        Flux::modal('pay-scale')->close();
        $this->dispatch('toast', message: __('Pay scale profile saved.'), variant: 'success');
    }

    // ---- Helpers -------------------------------------------------------------------------------

    /** What is typed in the modal as a salary (empty amounts left out). */
    private function typedSalary(): SalaryBreakdown
    {
        $heads = $this->heads()->keyBy('id');
        $lines = [];
        foreach ($this->lines as $headId => $amount) {
            $head = $heads[$headId] ?? null;
            if ($head === null || ! is_numeric($amount)) {
                continue;
            }
            $type = ProfileValueType::tryFrom($this->workedOut[$headId]['value_type'] ?? '');
            $rate = $type ? (string) $this->workedOut[$headId]['rate'] : null;
            $lines[] = new SalaryLine($head, '', Money::of((string) $amount), $type, is_numeric($rate) ? $rate : null);
        }

        return SalaryBreakdown::fromLines($lines);
    }

    /**
     * Heads that can be set per employee: the basic, and heads that are not formula / per attendance day; active, or
     * already typed in the modal.
     *
     * @return Collection<int, SalaryHead>
     */
    private function heads(): Collection
    {
        return SalaryHead::query()
            ->where(fn ($q) => $q->where('is_basic', true)->orWhereNotIn('calc_method', [CalcMethod::Formula, CalcMethod::Attendance]))
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', array_keys($this->lines)))
            ->ordered()->get();
    }

    /** 404 outside your scope; 403 without salary view rights. */
    private function employee(): Employee
    {
        $employee = Employee::findOrFail($this->employeeId);
        Gate::authorize('view', $employee);
        Gate::authorize('payroll.salary.view');

        return $employee;
    }

    private function authorizeManage(): Employee
    {
        $employee = $this->employee();
        Gate::authorize('payroll.salary.manage');

        return $employee;
    }

    public function render(): View
    {
        $employee = $this->employee()->load(['salaryProfile', 'payScaleProfile']);

        $history = EmployeeSalary::historyOf($employee)->map(fn ($rows, string $from) => (object) [
            'from' => $rows->first()->effective_from,
            'to' => $rows->first()->effective_to,
            'salary' => EmployeeSalary::breakdown($rows),
            'profile' => $rows->first()->profile?->name,
        ]);

        $today = today();
        $current = $history->first(fn ($s) => $s->from->lte($today) && ($s->to === null || $s->to->gte($today)));
        $upcoming = $history->first(fn ($s) => $s->from->gt($today));

        return view('payroll::livewire.employee-salary-tab', [
            'employee' => $employee,
            'history' => $history,
            'current' => $current,
            'upcoming' => $upcoming,
            'canManage' => Gate::allows('payroll.salary.manage'),
            'heads' => $this->heads(),
            'typed' => $this->typedSalary(),
            'profiles' => SalaryProfile::where('is_active', true)->orWhere('id', (int) ($this->form['salary_profile_id'] ?: 0))->orderBy('name')->pluck('name', 'id')->all(),
            'bases' => SalaryBasis::options(),
            'defaultPayScale' => app(PayrollSettings::class)->defaultPayScale(),
            'payScales' => PayScaleProfile::where('is_active', true)->orWhere('id', (int) ($this->payScaleId ?: 0))->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }
}
