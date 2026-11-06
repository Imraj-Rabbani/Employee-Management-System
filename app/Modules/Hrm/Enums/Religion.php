<?php

namespace App\Modules\Hrm\Enums;

use App\Core\Enums\HasOptions;

/**
 * Religion (legacy `Religions`). Also used for festival bonus rules (Phase 10.11).
 * Backed by the SAME integers as legacy, so migrated data (Phase 15) keeps its values.
 * Legacy 0 = "None" is not a case: store NULL instead.
 *
 *     Religion::from(1)          Religion::Islam
 *     $employee->religion->label()   (with a model cast: 'religion' => Religion::class)
 */
enum Religion: int
{
    use HasOptions;

    case Islam = 1;
    case Hinduism = 2;
    case Christianity = 3;
    case Buddhism = 4;
    case Other = 5;

    public function label(): string
    {
        return match ($this) {
            self::Islam => __('Islam'),
            self::Hinduism => __('Hinduism'),
            self::Christianity => __('Christianity'),
            self::Buddhism => __('Buddhism'),
            self::Other => __('Other'),
        };
    }
}
