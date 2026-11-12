<?php

namespace App\Modules\Hrm\Enums;

use App\Core\Enums\HasOptions;

/**
 * "Sandwich rule" (legacy `leavetypes.bridgingType`): weekends / holidays right before or after the leave
 * are added to it, so nobody takes Thursday + Sunday off and gets a free long weekend. Checked in 8.3. [LV-01]
 *
 *     None       not added
 *     OneSided   added on ONE side (the shorter run of days off, as in legacy)
 *     TwoSided   added on both sides
 */
enum LeaveBridging: int
{
    use HasOptions;

    case None = 0;
    case OneSided = 1;
    case TwoSided = 2;

    public function label(): string
    {
        return match ($this) {
            self::None => __('Not added'),
            self::OneSided => __('Added on one side'),
            self::TwoSided => __('Added on both sides'),
        };
    }
}
