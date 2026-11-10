<?php

namespace App\Modules\Hrm\Enums;

use App\Core\Enums\HasOptions;

/**
 * How a family member or nominee is related to the employee (legacy `EmployeeRelations`).
 * Same integers as legacy (Phase 15 import). Legacy 0 = "None" → NULL.
 */
enum EmployeeRelation: int
{
    use HasOptions;

    case Father = 1;
    case Mother = 2;
    case Brother = 3;
    case Sister = 4;
    case Son = 5;
    case Daughter = 6;
    case Spouse = 7;
    case FatherInLaw = 8;
    case MotherInLaw = 9;
    case BrotherInLaw = 10;
    case SisterInLaw = 11;
    case Uncle = 12;
    case Aunt = 13;
    case Cousin = 14;
    case Siblings = 15;
    case Self = 16;

    public function label(): string
    {
        return match ($this) {
            self::Father => __('Father'),
            self::Mother => __('Mother'),
            self::Brother => __('Brother'),
            self::Sister => __('Sister'),
            self::Son => __('Son'),
            self::Daughter => __('Daughter'),
            self::Spouse => __('Spouse'),
            self::FatherInLaw => __('Father-in-law'),
            self::MotherInLaw => __('Mother-in-law'),
            self::BrotherInLaw => __('Brother-in-law'),
            self::SisterInLaw => __('Sister-in-law'),
            self::Uncle => __('Uncle'),
            self::Aunt => __('Aunt'),
            self::Cousin => __('Cousin'),
            self::Siblings => __('Siblings'),
            self::Self => __('Self'),
        };
    }
}
