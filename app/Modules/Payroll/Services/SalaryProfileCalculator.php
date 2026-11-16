<?php

namespace App\Modules\Payroll\Services;

use App\Core\Support\Money;
use App\Modules\Payroll\Data\SalaryBreakdown;
use App\Modules\Payroll\Data\SalaryLine;
use App\Modules\Payroll\Enums\HeadType;
use App\Modules\Payroll\Enums\ProfileValueType;
use App\Modules\Payroll\Enums\SalaryBasis;
use App\Modules\Payroll\Exceptions\SalaryProfileException;
use App\Modules\Payroll\Models\SalaryHead;
use App\Modules\Payroll\Models\SalaryProfile;
use App\Modules\Payroll\Models\SalaryProfileItem;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * "Apply profile": a salary profile + a basic OR a gross → the amount of every head. [PC-02]
 *
 *     $calc->forProfile($profile, SalaryBasis::Gross, Money::of(30000))->amounts()
 *
 * Gross = basic + the earnings counted in gross. With F = fixed amounts, Pb = % of basic and Pg = % of gross
 * (all summed over those earnings, as fractions):
 *
 *     gross = basic + F + Pb·basic + Pg·gross
 *     from basic:  gross = (basic·(1 + Pb) + F) / (1 − Pg)
 *     from gross:  basic = (gross·(1 − Pg) − F) / (1 + Pb)
 *
 * Example (Bangladesh labour-law style): HR 50% of basic, medical 750, conveyance 450, food 1,250; gross 30,000
 * → basic = (30,000 − 2,450) / 1.5 = 18,366.67. From gross, rounding cents are given to the basic so the heads add
 * up to EXACTLY the gross that was typed. Deductions and info heads use the same basic / gross; never in gross.
 *
 * No database writes: saving the result as an employee's salary is 9.4.
 */
class SalaryProfileCalculator
{
    public function forProfile(SalaryProfile $profile, SalaryBasis $basis, Money $amount): SalaryBreakdown
    {
        $basicHead = SalaryHead::basic()
            ?? throw new SalaryProfileException(__('Mark one salary head as the basic salary first (Salary heads tab).'));

        return $this->apply($basicHead, $profile->items()->with('head')->get()->all(), $basis, $amount);
    }

    /**
     * Also for items that are not saved yet (the profile form's live preview): each item needs its head set.
     *
     * @param  list<SalaryProfileItem>  $items
     */
    public function apply(SalaryHead $basicHead, array $items, SalaryBasis $basis, Money $amount): SalaryBreakdown
    {
        if (! $amount->isPositive()) {
            throw new SalaryProfileException(__('Enter a basic or gross above zero.'));
        }

        // Sum up the earnings counted in gross: fixed amounts, and percents as fractions (50 → 0.5).
        $fixed = BigDecimal::zero();
        $ofBasic = BigDecimal::zero();
        $ofGross = BigDecimal::zero();
        foreach ($items as $item) {
            if (! $this->inGross($item->head)) {
                continue;
            }
            match ($item->value_type) {
                ProfileValueType::Amount => $fixed = $fixed->plus(($item->amount ?? Money::zero())->toDecimal()),
                ProfileValueType::PercentOfBasic => $ofBasic = $ofBasic->plus($this->fraction($item->rate)),
                ProfileValueType::PercentOfGross => $ofGross = $ofGross->plus($this->fraction($item->rate)),
            };
        }

        if ($ofGross->isGreaterThanOrEqualTo(1)) {
            throw new SalaryProfileException(__('The earnings set as % of gross add up to 100% or more, so there is no room for the basic.'));
        }

        $given = BigDecimal::of($amount->toDecimal());
        if ($basis === SalaryBasis::Basic) {
            $basic = $amount;
            $gross = $this->money($given->multipliedBy($ofBasic->plus(1))->plus($fixed)->dividedBy(BigDecimal::one()->minus($ofGross), 6, RoundingMode::HalfUp));
        } else {
            $gross = $amount;
            $basic = $this->money($given->multipliedBy(BigDecimal::one()->minus($ofGross))->minus($fixed)->dividedBy($ofBasic->plus(1), 6, RoundingMode::HalfUp));
            if (! $basic->isPositive()) {
                throw new SalaryProfileException(__('This gross is too small for the profile: the fixed amounts alone come to :amount.', ['amount' => $this->money($fixed)->format()]));
            }
        }

        // Every item's amount from the basic / gross.
        $lines = [];
        $otherInGross = Money::zero();
        foreach ($items as $item) {
            $value = match ($item->value_type) {
                ProfileValueType::Amount => $item->amount ?? Money::zero(),
                ProfileValueType::PercentOfBasic => $basic->percent((string) $item->rate),
                ProfileValueType::PercentOfGross => $gross->percent((string) $item->rate),
            };
            $line = new SalaryLine($item->head, $item->describe(), $value, $item->value_type->usesRate() ? $item->value_type : null, $item->value_type->usesRate() ? (string) $item->rate : null);
            $lines[] = $line;
            if ($line->inGross()) {
                $otherInGross = $otherInGross->add($value);
            }
        }

        if ($basis === SalaryBasis::Gross) {
            $basic = $gross->subtract($otherInGross);   // takes the rounding cents: heads add up to the gross exactly
        }

        $lines[] = new SalaryLine($basicHead, __('Basic'), $basic);

        // Totals from the lines (from basic: the real sum, which may be a cent off the formula).
        return SalaryBreakdown::fromLines($lines);
    }

    private function inGross(SalaryHead $head): bool
    {
        return $head->type === HeadType::Earning && $head->in_gross;
    }

    private function fraction(?string $rate): BigDecimal
    {
        return BigDecimal::of($rate ?? '0')->dividedBy(100, 6, RoundingMode::HalfUp);
    }

    private function money(BigDecimal $value): Money
    {
        return Money::of((string) $value);
    }
}
