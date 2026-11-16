<?php

namespace App\Modules\Payroll\Models;

use App\Core\Casts\MoneyCast;
use App\Core\Models\TenantModel;
use App\Core\Support\Money;
use App\Modules\Payroll\Database\Factories\SalaryHeadFactory;
use App\Modules\Payroll\Enums\CalcMethod;
use App\Modules\Payroll\Enums\HeadType;
use App\Modules\Payroll\Enums\PayCycle;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * A pay component with its calculation rules (legacy `salaryheads`).
 * Always listed by `sequence`: SalaryHead::ordered()->get().
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property HeadType $type
 * @property CalcMethod $calc_method
 * @property bool $is_basic THE basic salary: its amount is set per employee, the other heads are worked out from it
 * @property Money|null $amount fixed amount, or amount per day for Attendance
 * @property string|null $rate percent for the "% of ..." methods, decimal as a string
 * @property string|null $formula
 * @property bool $in_gross
 * @property string $taxable_percent
 * @property Money|null $tax_exemption_cap
 * @property bool $prorate
 * @property bool $arrear_eligible
 * @property PayCycle $pay_cycle
 * @property int $sequence
 * @property string|null $gl_account
 * @property int $eligible_after_months
 * @property bool $is_active
 */
#[UseFactory(SalaryHeadFactory::class)]
class SalaryHead extends TenantModel
{
    /** @use HasFactory<SalaryHeadFactory> */
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'type', 'calc_method', 'is_basic', 'amount', 'rate', 'formula', 'in_gross', 'taxable_percent',
        'tax_exemption_cap', 'prorate', 'arrear_eligible', 'pay_cycle', 'sequence', 'gl_account',
        'eligible_after_months', 'is_active',
    ];

    protected $attributes = [
        'is_basic' => false, 'in_gross' => true, 'taxable_percent' => 100, 'prorate' => true, 'arrear_eligible' => true,
        'pay_cycle' => 'monthly', 'sequence' => 0, 'eligible_after_months' => 0, 'is_active' => true,
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'type' => HeadType::class,
            'calc_method' => CalcMethod::class,
            'pay_cycle' => PayCycle::class,
            'amount' => MoneyCast::class,
            'rate' => 'decimal:4',
            'taxable_percent' => 'decimal:2',
            'tax_exemption_cap' => MoneyCast::class,
            'is_basic' => 'boolean',
            'in_gross' => 'boolean',
            'prorate' => 'boolean',
            'arrear_eligible' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @param  Builder<SalaryHead>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sequence')->orderBy('name');
    }

    /** The basic salary head, or null when none is marked yet. */
    public static function basic(): ?self
    {
        return self::where('is_basic', true)->first();
    }

    /** The rule in words, e.g. "50% of basic", "Fixed 5,000.00", "500.00 per attendance day". */
    public function rule(): string
    {
        if ($this->is_basic) {
            return __('Basic salary (set per employee)');
        }

        $rate = rtrim(rtrim((string) ($this->rate ?? '0'), '0'), '.') ?: '0';

        return match ($this->calc_method) {
            CalcMethod::Fixed => __('Fixed :amount', ['amount' => ($this->amount ?? Money::zero())->format()]),
            CalcMethod::PercentOfBasic => __(':rate% of basic', ['rate' => $rate]),
            CalcMethod::PercentOfGross => __(':rate% of gross', ['rate' => $rate]),
            CalcMethod::Attendance => __(':amount per attendance day', ['amount' => ($this->amount ?? Money::zero())->format()]),
            CalcMethod::Formula => __('Formula: :formula', ['formula' => $this->formula ?: '—']),
        };
    }

    /**
     * What the head comes to for a sample employee: the "rule preview". Null for a formula, because formulas are
     * evaluated by the payroll run.
     */
    public function preview(Money $basic, Money $gross, int $payableDays): ?Money
    {
        if ($this->is_basic) {
            return $basic;
        }

        $amount = $this->amount ?? Money::zero();
        $rate = (string) ($this->rate ?? '0');

        return match ($this->calc_method) {
            CalcMethod::Fixed => $amount,
            CalcMethod::PercentOfBasic => $basic->percent($rate),
            CalcMethod::PercentOfGross => $gross->percent($rate),
            CalcMethod::Attendance => $amount->multiply($payableDays),
            CalcMethod::Formula => null,
        };
    }

    /**
     * The part of an amount that is taxed: taxable % of it, minus the exemption cap (when set), never below 0.
     * Example: medical 10,000, taxable 100%, cap 5,000 → 5,000 is taxed.
     */
    public function taxablePart(Money $amount): Money
    {
        $taxable = $amount->percent((string) $this->taxable_percent);

        if ($this->tax_exemption_cap !== null) {
            $taxable = $taxable->subtract($this->tax_exemption_cap);
        }

        return $taxable->isNegative() ? Money::zero() : $taxable;
    }
}
