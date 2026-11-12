<?php

namespace App\Modules\Hrm\Enums;

use App\Core\Enums\HasOptions;

/**
 * The date "eligible after N months" counts from (legacy `leavetypes.eligibleFrom`). [LV-01]
 */
enum LeaveEligibleFrom: int
{
    use HasOptions;

    case JoinDate = 0;
    case ConfirmationDate = 1;

    public function label(): string
    {
        return match ($this) {
            self::JoinDate => __('Join date'),
            self::ConfirmationDate => __('Confirmation date'),
        };
    }
}
