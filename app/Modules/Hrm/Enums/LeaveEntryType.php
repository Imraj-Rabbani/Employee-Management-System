<?php

namespace App\Modules\Hrm\Enums;

use App\Core\Enums\HasOptions;

/**
 * Why a leave balance changed: one row of leave_ledger. [LV-03]
 *
 *     Opening         + the balance an employee starts with (e.g. from the old system)
 *     Earned          + entitlement / accrual (8.5)
 *     Taken           − approved leave (8.4); + when it is withdrawn (a reversal)
 *     Encashed        − paid out (8.6)
 *     CarriedForward  − in the year the days leave, + in the next year (8.5)
 *     Expired         − lapsed at year end, or carried days that ran out
 *     Adjustment      ± a correction by HR, always with a note
 */
enum LeaveEntryType: int
{
    use HasOptions;

    case Opening = 0;
    case Earned = 1;
    case Taken = 2;
    case Encashed = 3;
    case CarriedForward = 4;
    case Expired = 5;
    case Adjustment = 6;

    public function label(): string
    {
        return match ($this) {
            self::Opening => __('Opening balance'),
            self::Earned => __('Earned'),
            self::Taken => __('Taken'),
            self::Encashed => __('Encashed'),
            self::CarriedForward => __('Carried forward'),
            self::Expired => __('Expired'),
            self::Adjustment => __('Correction'),
        };
    }

    /** The leave_balances column this type is added up in. */
    public function balanceColumn(): string
    {
        return match ($this) {
            self::Opening => 'opening',
            self::Earned => 'earned',
            self::Taken => 'taken',
            self::Encashed => 'encashed',
            self::CarriedForward => 'carried_forward',
            self::Expired => 'expired',
            self::Adjustment => 'adjusted',
        };
    }

    /** Shown as a positive "used" number in the balance (taken, encashed, expired). */
    public function isUsage(): bool
    {
        return in_array($this, [self::Taken, self::Encashed, self::Expired], true);
    }
}
