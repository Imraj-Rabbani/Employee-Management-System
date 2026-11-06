<?php

namespace App\Modules\Hrm\Enums;

use App\Core\Enums\HasOptions;

/**
 * Marital status (legacy `MaritalStatus`).
 * Backed by the SAME integers as legacy, so migrated data (Phase 15) keeps its values.
 * Legacy 0 = "None" is not a case: store NULL instead.
 *
 *     MaritalStatus::from(1)          MaritalStatus::Single
 *     $employee->marital_status->label()   (with a model cast: 'marital_status' => MaritalStatus::class)
 */
enum MaritalStatus: int
{
    use HasOptions;

    case Single = 1;
    case Married = 2;
    case Divorced = 3;
    case Widowed = 4;

    public function label(): string
    {
        return match ($this) {
            self::Single => __('Single'),
            self::Married => __('Married'),
            self::Divorced => __('Divorced'),
            self::Widowed => __('Widow/Widower'),
        };
    }
}
