<?php

namespace App\Modules\Hrm\Enums;

use App\Core\Enums\HasOptions;

/**
 * Where an interview is (interviews.status, 6.6). [REC-05]
 *
 *     Scheduled   planned; panelists can score it (also afterwards, until it is completed)
 *     Completed   HR closed it: the scores count for the ranking, no more changes
 *     Cancelled   did not happen; ignored by the ranking
 */
enum InterviewStatus: string
{
    use HasOptions;

    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => __('Scheduled'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Scheduled => 'info',
            self::Completed => 'success',
            self::Cancelled => 'neutral',
        };
    }
}
