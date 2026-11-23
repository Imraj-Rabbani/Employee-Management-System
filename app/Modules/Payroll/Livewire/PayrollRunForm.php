<?php

namespace App\Modules\Payroll\Livewire;

use App\Core\Support\Period;
use App\Core\Support\PeriodCycle;
use App\Modules\Hrm\Enums\Religion;
use App\Modules\Hrm\Services\EmployeeVisibility;
use App\Modules\Payroll\Actions\CreatePayrollRun;
use App\Modules\Payroll\Data\NewPayrollRun;
use App\Modules\Payroll\Enums\AmountBasis;
use App\Modules\Payroll\Enums\HeadType;
use App\Modules\Payroll\Enums\PayrollRunType;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\SalaryHead;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * New / Edit / Delete payroll run. Modal "payroll-run-form". Permission payroll.run.process.
 *
 *   New    period (year, month, cycle), type (+ head for head-wise), scope (entity or whole company, optionally only
 *          some employee codes), pay date, note → CreatePayrollRun (all rules are there).
 *   Edit   only the pay date and note; period / type / scope are fixed once made (delete and make again).
 *   Delete only while the run is a draft or processed (not submitted yet). Its employees and items go with it.
 *
 * Events: "create-payroll-run", "edit-payroll-run" {id}, "delete-payroll-run" {ids}. Sends "payroll-run-saved".
 */
class PayrollRunForm extends Component
{
    /** null = creating a new run */
    #[Locked]
    public ?int $recordId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** Starts as this month's regular run for the whole company (or the user's own entity). */
    #[On('create-payroll-run')]
    public function create(): void
    {
        Gate::authorize('payroll.run.process');
        $limited = app(EmployeeVisibility::class)->entityIdsFor(Auth::user()) !== null;

        $this->recordId = null;
        $this->form = [
            'year' => (string) now()->year,
            'month' => (string) now()->month,
            'cycle' => PeriodCycle::Monthly->value,
            'run_type' => PayrollRunType::Regular->value,
            'salary_head_id' => '',
            'amount_basis' => AmountBasis::HeadRule->value,
            'religions' => [],
            'confirmed_only' => false,
            'joined_before' => '',
            'prorate_by_service' => false,
            'entity_id' => $limited ? (string) (array_key_first($this->entities()) ?? '') : '',
            'employee_codes' => '',
            'pay_date' => '',
            'note' => '',
        ];
        $this->resetValidation();

        Flux::modal('payroll-run-form')->show();
    }

    #[On('edit-payroll-run')]
    public function edit(int $id): void
    {
        $run = $this->find($id);

        $this->recordId = $run->id;
        $this->form = [
            'pay_date' => $run->pay_date?->toDateString() ?? '',
            'note' => $run->note ?? '',
        ];
        $this->resetValidation();

        Flux::modal('payroll-run-form')->show();
    }

    public function save(CreatePayrollRun $action): void
    {
        $this->recordId ? $this->update() : $this->store($action);
    }

    private function store(CreatePayrollRun $action): void
    {
        Gate::authorize('payroll.run.process');
        $values = $this->validate([
            'form.year' => ['required', 'integer', 'between:2000,2100'],
            'form.month' => ['required', 'integer', 'between:1,12'],
            'form.cycle' => ['required', Rule::enum(PeriodCycle::class)],
            'form.run_type' => ['required', Rule::in([PayrollRunType::Regular->value, PayrollRunType::HeadWise->value, PayrollRunType::OffCycle->value])],
            'form.salary_head_id' => ['nullable', 'integer'],
            'form.amount_basis' => ['nullable', Rule::enum(AmountBasis::class)],
            'form.religions' => ['array'],
            'form.religions.*' => [Rule::enum(Religion::class)],
            'form.confirmed_only' => ['boolean'],
            'form.joined_before' => ['nullable', 'date'],
            'form.prorate_by_service' => ['boolean'],
            'form.entity_id' => ['nullable', 'integer'],
            'form.employee_codes' => ['nullable', 'string', 'max:20000'],
            'form.pay_date' => ['nullable', 'date'],
            'form.note' => ['nullable', 'string', 'max:500'],
        ], attributes: [
            'form.run_type' => __('type'), 'form.entity_id' => __('scope'), 'form.pay_date' => __('pay date'),
        ])['form'];

        $input = new NewPayrollRun(
            period: Period::of((int) $values['year'], (int) $values['month'], PeriodCycle::from($values['cycle'])),
            type: PayrollRunType::from($values['run_type']),
            entityId: ($values['entity_id'] ?? '') === '' ? null : (int) $values['entity_id'],
            salaryHeadId: ($values['salary_head_id'] ?? '') === '' ? null : (int) $values['salary_head_id'],
            employeeCodes: self::codes($values['employee_codes'] ?? ''),
            payDate: ($values['pay_date'] ?? '') === '' ? null : CarbonImmutable::parse($values['pay_date']),
            note: ($values['note'] ?? '') === '' ? null : $values['note'],
            amountBasis: ($values['amount_basis'] ?? '') === '' ? null : AmountBasis::from($values['amount_basis']),
            religions: array_map('intval', $values['religions'] ?? []),
            confirmedOnly: (bool) ($values['confirmed_only'] ?? false),
            joinedBefore: ($values['joined_before'] ?? '') === '' ? null : CarbonImmutable::parse($values['joined_before']),
            prorateByService: (bool) ($values['prorate_by_service'] ?? false),
        );

        if ($problem = $action->problem($input, Auth::user())) {
            $this->addError('form.'.$problem['field'], $problem['message']);

            return;
        }
        $run = $action->handle($input, Auth::user());

        Flux::modal('payroll-run-form')->close();
        $this->dispatch('payroll-run-saved');
        $this->dispatch('toast', message: __(':code made with :count employees.', ['code' => $run->code, 'count' => $run->employee_count]), variant: 'success');
    }

    private function update(): void
    {
        $run = $this->find((int) $this->recordId);
        if (! Gate::allows('update', $run)) {
            $this->addError('form.note', __('This run has been submitted, so it cannot be changed.'));

            return;
        }

        $values = $this->validate([
            'form.pay_date' => ['nullable', 'date', 'after_or_equal:'.$run->period_start->toDateString()],
            'form.note' => ['nullable', 'string', 'max:500'],
        ], attributes: ['form.pay_date' => __('pay date')])['form'];

        $run->update([
            'pay_date' => ($values['pay_date'] ?? '') === '' ? null : $values['pay_date'],
            'note' => ($values['note'] ?? '') === '' ? null : $values['note'],
        ]);

        Flux::modal('payroll-run-form')->close();
        $this->dispatch('payroll-run-saved');
        $this->dispatch('toast', message: __(':code saved.', ['code' => $run->code]), variant: 'success');
    }

    public function delete(): void
    {
        $this->deleteMany([(int) $this->recordId]);
        Flux::modal('payroll-run-form')->close();
    }

    /**
     * Delete from the toolbar: every ticked run that may go; one toast says what happened.
     *
     * @param  list<int|string>  $ids
     */
    #[On('delete-payroll-run')]
    public function deleteMany(array $ids): void
    {
        Gate::authorize('payroll.run.process');
        $deleted = 0;
        $problems = [];
        foreach (PayrollRun::query()->visibleTo(Auth::user())->whereKey($ids)->get() as $run) {
            if (! Gate::allows('delete', $run)) {
                $problems[] = $run->code.': '.__('only a run not yet submitted can be deleted.');

                continue;
            }
            // Employees and items go with it (cascade); adjustments it took become free again.
            $run->getConnection()->transaction(fn () => $run->delete());
            $deleted++;
        }

        $this->dispatch('payroll-run-saved');
        $this->dispatch('toast',
            message: implode(' ', array_filter([$deleted > 0 ? trans_choice(':count deleted.|:count deleted.', $deleted) : null, ...$problems])),
            variant: $problems === [] ? 'success' : ($deleted > 0 ? 'warning' : 'error'),
        );
    }

    /**
     * "E001, E002\nE003" → ['E001', 'E002', 'E003']: commas, spaces or new lines between codes.
     *
     * @return list<string>
     */
    public static function codes(string $text): array
    {
        return array_values(array_unique(array_filter(preg_split('/[\s,;]+/', trim($text)) ?: [], fn (string $c) => $c !== '')));
    }

    /** Only runs the user may see: another entity's run id gives 404. */
    private function find(int $id): PayrollRun
    {
        Gate::authorize('payroll.run.process');

        return PayrollRun::query()->visibleTo(Auth::user())->findOrFail($id);
    }

    /** @return array<int, string> */
    private function entities(): array
    {
        return app(EmployeeVisibility::class)->documentEntityOptionsFor(Auth::user());
    }

    public function render(): View
    {
        $run = $this->recordId ? PayrollRun::query()->visibleTo(Auth::user())->with(['entity', 'head'])->find($this->recordId) : null;

        return view('payroll::livewire.payroll-run-form', [
            'run' => $run,
            'units' => $this->entities(),
            'wholeCompany' => app(EmployeeVisibility::class)->entityIdsFor(Auth::user()) === null,
            'heads' => $run ? [] : SalaryHead::query()->where('type', HeadType::Earning)->where('is_active', true)->where('is_basic', false)->ordered()->pluck('name', 'id')->all(),
            'bases' => AmountBasis::cases(),
            'religionOptions' => Religion::options(),
            'types' => array_intersect_key(PayrollRunType::options(), array_flip([PayrollRunType::Regular->value, PayrollRunType::HeadWise->value, PayrollRunType::OffCycle->value])),
        ]);
    }
}
