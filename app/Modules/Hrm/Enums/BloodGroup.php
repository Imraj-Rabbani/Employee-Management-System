<?php

namespace App\Modules\Hrm\Enums;

use App\Core\Enums\HasOptions;

/**
 * Blood group (legacy `BloodGroups`). The numbers are legacy's, not in a natural order: keep them.
 * Backed by the SAME integers as legacy, so migrated data (Phase 15) keeps its values.
 * Legacy 0 = "None" is not a case: store NULL instead.
 *
 *     BloodGroup::from(1)          BloodGroup::APositive
 *     $employee->blood_group->label()   (with a model cast: 'blood_group' => BloodGroup::class)
 */
enum BloodGroup: int
{
    use HasOptions;

    case APositive = 1;
    case BPositive = 2;
    case ABPositive = 3;
    case OPositive = 4;
    case ANegative = 5;
    case BNegative = 6;
    case ABNegative = 7;
    case ONegative = 8;

    public function label(): string
    {
        // Symbols are the same in every language, so no __() here.
        return match ($this) {
            self::APositive => 'A+',
            self::BPositive => 'B+',
            self::ABPositive => 'AB+',
            self::OPositive => 'O+',
            self::ANegative => 'A-',
            self::BNegative => 'B-',
            self::ABNegative => 'AB-',
            self::ONegative => 'O-',
        };
    }
}
