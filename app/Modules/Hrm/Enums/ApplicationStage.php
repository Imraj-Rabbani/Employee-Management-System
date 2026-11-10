<?php

namespace App\Modules\Hrm\Enums;

use App\Core\Enums\HasOptions;

/**
 * Where a job application is in the hiring pipeline (applications.stage, Phase 6). Not in legacy.
 * String-backed (like ImportStatus), so the database shows "shortlisted", not a number.
 *
 *     applied → shortlisted → interview → offered → hired
 *     ... or at any open stage → rejected (HR says no) / withdrawn (the applicant says no)
 *
 *     ApplicationStage::Offered->color()        'info'   for <x-ui.badge>
 *     ApplicationStage::Rejected->isFinal()     true     the application is closed
 *     ApplicationStage::Withdrawn->needsReason() true    the stage change form asks why (6.5)
 */
enum ApplicationStage: string
{
    use HasOptions;

    case Applied = 'applied';
    case Shortlisted = 'shortlisted';
    case Interview = 'interview';
    case Offered = 'offered';
    case Hired = 'hired';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Applied => __('Applied'),
            self::Shortlisted => __('Shortlisted'),
            self::Interview => __('Interview'),
            self::Offered => __('Offered'),
            self::Hired => __('Hired'),
            self::Rejected => __('Rejected'),
            self::Withdrawn => __('Withdrawn'),
        };
    }

    /** Colour for <x-ui.badge>. */
    public function color(): string
    {
        return match ($this) {
            self::Applied, self::Shortlisted => 'neutral',
            self::Interview, self::Offered => 'info',
            self::Hired => 'success',
            self::Rejected => 'danger',
            self::Withdrawn => 'warning',
        };
    }

    /** Closed: no more stage changes (hired, rejected or withdrawn). */
    public function isFinal(): bool
    {
        return in_array($this, [self::Hired, self::Rejected, self::Withdrawn], true);
    }

    /**
     * Place in the pipeline: applied 1 → shortlisted 2 → interview 3 → offered 4 → hired 5.
     * Rejected / withdrawn are not "steps" (they end the application from any open stage): null.
     */
    public function step(): ?int
    {
        return match ($this) {
            self::Applied => 1,
            self::Shortlisted => 2,
            self::Interview => 3,
            self::Offered => 4,
            self::Hired => 5,
            self::Rejected, self::Withdrawn => null,
        };
    }

    /** Rejected and withdrawn need a reason (REC-04). */
    public function needsReason(): bool
    {
        return $this === self::Rejected || $this === self::Withdrawn;
    }
}
