<?php

namespace App\Modules\Hrm\Enums;

use App\Core\Enums\HasOptions;

/**
 * Where a job posting is (job_postings.status, 6.3). [REC-02]
 *
 *     draft → open → closed      (closed can be reopened, e.g. too few good applicants)
 *
 *     Draft    HR is still writing it; no applications yet
 *     Open     applications can be entered (6.4), between open_date and close_date
 *     Closed   no new applications; the pipeline (6.5) still continues for the existing ones
 */
enum JobPostingStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Open => __('Open'),
            self::Closed => __('Closed'),
        };
    }

    /** Colour for <x-ui.badge>. */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Open => 'success',
            self::Closed => 'warning',
        };
    }
}
