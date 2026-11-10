<?php

namespace App\Modules\Hrm\Enums;

use App\Core\Enums\HasOptions;

/**
 * Gender of an employee or family member (legacy `Genders`).
 * Backed by the SAME integers as legacy, so migrated data (Phase 15) keeps its values.
 * Legacy 0 = "None" is not a case: store NULL instead.
 *
 *     Gender::from(1)          Gender::Male
 *     $employee->gender->label()   (with a model cast: 'gender' => Gender::class)
 */
enum Gender: int
{
    use HasOptions;

    case Male = 1;
    case Female = 2;

    public function label(): string
    {
        return match ($this) {
            self::Male => __('Male'),
            self::Female => __('Female'),
        };
    }
}
