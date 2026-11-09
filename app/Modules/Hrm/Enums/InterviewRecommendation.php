<?php

namespace App\Modules\Hrm\Enums;

use App\Core\Enums\HasOptions;

/** A panelist's overall advice on their score sheet (interview_panelists.recommendation, 6.6). [REC-05] */
enum InterviewRecommendation: string
{
    use HasOptions;

    case Recommend = 'recommend';
    case Consider = 'consider';
    case NotRecommend = 'not_recommend';

    public function label(): string
    {
        return match ($this) {
            self::Recommend => __('Recommend'),
            self::Consider => __('Maybe'),
            self::NotRecommend => __('Do not recommend'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Recommend => 'success',
            self::Consider => 'warning',
            self::NotRecommend => 'danger',
        };
    }
}
