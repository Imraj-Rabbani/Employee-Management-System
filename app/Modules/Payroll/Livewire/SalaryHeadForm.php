<?php

namespace App\Modules\Payroll\Livewire;

use App\Core\Livewire\LookupForm;
use App\Core\Support\Money;
use App\Modules\Payroll\Enums\CalcMethod;
use App\Modules\Payroll\Enums\HeadRole;
use App\Modules\Payroll\Enums\HeadType;
use App\Modules\Payroll\Enums\PayCycle;
use App\Modules\Payroll\Models\SalaryHead;
use App\Modules\Payroll\Services\PayrollSettings;
use Illuminate\Validation\Rule;

/**
 * New / Edit / Delete salary head (modal "salary-head-form").
 * Besides the fields, the form shows a RULE PREVIEW: type a sample basic / gross / days and see what the head
 * would come to, live, before saving. The sample values are not saved.
 */
class SalaryHeadForm extends LookupForm
{
    // Sample employee for the preview only (not part of $form, never saved).
    public string $sampleBasic = '50000';

    public string $sampleGross = '80000';

    public int $sampleDays = 26;

    protected function key(): string
    {
        return 'salary-head';
    }

    protected function model(): string
    {
        return SalaryHead::class;
    }

    protected function defaults(): array
    {
        return [
            'code' => '', 'name' => '',
            'type' => HeadType::Earning->value, 'calc_method' => CalcMethod::Fixed->value, 'is_basic' => false,
            'amount' => '', 'rate' => '', 'formula' => '',
            'in_gross' => true, 'taxable_percent' => '100', 'tax_exemption_cap' => '',
            'prorate' => true, 'arrear_eligible' => true,
            'pay_cycle' => PayCycle::Monthly->value, 'sequence' => 1,
            'gl_account' => '', 'eligible_after_months' => 0, 'is_active' => true,
        ];
    }

    protected function fieldRules(): array
    {
        // The basic head has no rule of its own (its amount is set per employee), so no amount / rate / formula.
        $method = fn () => ($this->form['is_basic'] ?? false) ? null : CalcMethod::tryFrom((string) ($this->form['calc_method'] ?? ''));

        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z][A-Za-z0-9_]*$/', Rule::unique(SalaryHead::class, 'code')->ignore($this->recordId)],
            'name' => ['required', 'string', 'max:100', Rule::unique(SalaryHead::class, 'name')->ignore($this->recordId)],
            'type' => ['required', Rule::enum(HeadType::class)],
            'calc_method' => ['required', Rule::enum(CalcMethod::class)],
            'is_basic' => ['boolean'],
            'amount' => [Rule::requiredIf(fn () => $method()?->usesAmount() ?? false), 'nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'rate' => [Rule::requiredIf(fn () => $method()?->usesRate() ?? false), 'nullable', 'numeric', 'min:0', 'max:1000'],   // DECIMAL(9,4); over 1000 % is a typo
            'formula' => [Rule::requiredIf(fn () => $method() === CalcMethod::Formula), 'nullable', 'string', 'max:500'],
            'in_gross' => ['boolean'],
            'taxable_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'tax_exemption_cap' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'prorate' => ['boolean'],
            'arrear_eligible' => ['boolean'],
            'pay_cycle' => ['required', Rule::enum(PayCycle::class)],
            'sequence' => ['required', 'integer', 'min:0', 'max:9999'],
            'gl_account' => ['nullable', 'string', 'max:50'],
            'eligible_after_months' => ['required', 'integer', 'min:0', 'max:600'],
            'is_active' => ['boolean'],
        ];
    }

    /** Only ONE basic head, and it must be an earning counted in gross (every profile builds the gross on it). */
    protected function check(array $values): bool
    {
        if (! ($values['is_basic'] ?? false)) {
            return true;
        }

        $other = SalaryHead::where('is_basic', true)->whereKeyNot($this->recordId ?? 0)->value('name');
        if ($other !== null) {
            $this->addError('form.is_basic', __(':name is already the basic salary head.', ['name' => $other]));

            return false;
        }

        if ($values['type'] !== HeadType::Earning->value || ! $values['in_gross']) {
            $this->addError('form.is_basic', __('The basic salary must be an earning counted in gross salary.'));

            return false;
        }

        return true;
    }

    /** A head the payroll settings use (tax, overtime ...) cannot be deleted: the run would lose it. */
    public function delete(): void
    {
        $head = SalaryHead::find($this->recordId);
        $roles = $head ? app(PayrollSettings::class)->rolesOf($head) : [];
        if ($roles !== []) {
            $this->addError('form.code', __('The payroll settings use this head for: :roles. Choose another head there first.', [
                'roles' => implode(', ', array_map(fn (HeadRole $r) => $r->label(), $roles)),
            ]));

            return;
        }

        parent::delete();
    }

    protected function labels(): array
    {
        return [
            'calc_method' => __('calculation'),
            'rate' => __('percent'),
            'taxable_percent' => __('taxable percent'),
            'tax_exemption_cap' => __('exemption cap'),
            'gl_account' => __('GL account'),
            'eligible_after_months' => __('eligible after (months)'),
        ];
    }

    protected function view(): string
    {
        return 'payroll::livewire.salary-head-form';
    }

    protected function viewData(): array
    {
        return [
            'types' => HeadType::options(),
            'methods' => CalcMethod::options(),
            'cycles' => PayCycle::options(),
            'preview' => $this->preview(),
        ];
    }

    /**
     * The rule in words + the amount for the sample employee, from the values currently typed in the form
     * (an unsaved SalaryHead, so the same casts and rule() / preview() as a saved head).
     *
     * @return array{rule: string, amount: ?string, taxable: ?string, formula: bool}|null
     */
    private function preview(): ?array
    {
        $method = CalcMethod::tryFrom((string) ($this->form['calc_method'] ?? ''));
        if ($method === null) {
            return null;
        }

        $head = new SalaryHead([
            'calc_method' => $method,
            'is_basic' => (bool) ($this->form['is_basic'] ?? false),
            'amount' => $this->numeric($this->form['amount'] ?? null),
            'rate' => $this->numeric($this->form['rate'] ?? null),
            'formula' => $this->form['formula'] ?? null,
            'taxable_percent' => $this->numeric($this->form['taxable_percent'] ?? null) ?? 100,
            'tax_exemption_cap' => $this->numeric($this->form['tax_exemption_cap'] ?? null),
        ]);

        $amount = $head->preview(
            Money::of($this->numeric($this->sampleBasic) ?? 0),
            Money::of($this->numeric($this->sampleGross) ?? 0),
            max(0, $this->sampleDays),
        );

        return [
            'rule' => $head->rule(),
            'amount' => $amount?->format(),
            'taxable' => $amount ? $head->taxablePart($amount)->format() : null,
            'formula' => $method === CalcMethod::Formula && ! $head->is_basic,
        ];
    }

    /** A usable number from what was typed, or null (so half-typed input never breaks the preview). */
    private function numeric(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return is_numeric($value) ? (string) $value : null;
    }
}
