<?php

namespace App\Modules\Payroll\Enums;

use App\Core\Enums\HasOptions;

/** What a head is worth in a salary profile. */
enum ProfileValueType: string
{
    use HasOptions;

    case Amount = 'amount';                  // a fixed amount (uses "amount")
    case PercentOfBasic = 'percent_basic';   // rate % of basic
    case PercentOfGross = 'percent_gross';   // rate % of gross

    public function label(): string
    {
        return match ($this) {
            self::Amount => __('Amount'),
            self::PercentOfBasic => __('% of basic'),
            self::PercentOfGross => __('% of gross'),
        };
    }

    public function usesRate(): bool
    {
        return $this !== self::Amount;
    }

    /** The head's own rule as the starting value type in a profile (Fixed → amount, % basic → % basic ...). */
    public static function fromCalcMethod(CalcMethod $method): self
    {
        return match ($method) {
            CalcMethod::PercentOfBasic => self::PercentOfBasic,
            CalcMethod::PercentOfGross => self::PercentOfGross,
            default => self::Amount,
        };
    }
}
