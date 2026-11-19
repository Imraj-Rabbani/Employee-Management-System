<?php

namespace App\Modules\Payroll\Enums;

use App\Core\Enums\HasOptions;

/**
 * Where a payroll run is (payroll_runs.status). Architecture §9.1:
 *
 *     draft ──process──▶ processing ──▶ processed ──submit──▶ pending ──approve──▶ approved ──lock──▶ locked ──▶ disbursed
 *       ▲                   │               │  ▲                  │
 *       └── batch failed ───┘    re-run ────┘  └──── rejected / sent back ──┘
 *
 *     $run->status->canMoveTo(PayrollRunStatus::Pending)   may this run be submitted now?
 *     $run->status->isEditable()                           may it be (re)calculated / changed?
 */
enum PayrollRunStatus: string
{
    use HasOptions;

    case Draft = 'draft';             // made, not calculated yet
    case Processing = 'processing';   // the job batch is calculating
    case Processed = 'processed';     // calculated: review, re-run, then submit
    case Pending = 'pending';         // submitted, waiting for approvers
    case Approved = 'approved';
    case Locked = 'locked';           // final: nothing may change any more
    case Disbursed = 'disbursed';     // paid out (bank file / cash)

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Processing => __('Processing'),
            self::Processed => __('Processed'),
            self::Pending => __('Waiting for approval'),
            self::Approved => __('Approved'),
            self::Locked => __('Locked'),
            self::Disbursed => __('Disbursed'),
        };
    }

    /** Colour for <x-ui.badge>. */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Processing, self::Pending => 'warning',
            self::Processed, self::Approved => 'info',
            self::Locked, self::Disbursed => 'success',
        };
    }

    /**
     * The statuses this one may go to next.
     *
     * @return list<self>
     */
    public function next(): array
    {
        return match ($this) {
            self::Draft => [self::Processing],
            self::Processing => [self::Processed, self::Draft],     // done, or the batch failed
            self::Processed => [self::Processing, self::Pending],   // re-run, or submit
            self::Pending => [self::Approved, self::Processed],     // approved, or rejected / sent back
            self::Approved => [self::Locked],
            self::Locked => [self::Disbursed],
            self::Disbursed => [],
        };
    }

    public function canMoveTo(self $to): bool
    {
        return in_array($to, $this->next(), true);
    }

    /** Draft or processed: the run may be (re)calculated, its employees and amounts changed, or deleted. */
    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Processed;
    }

    /** Locked or disbursed: the numbers are final and may never change (10.10). */
    public function isFinal(): bool
    {
        return $this === self::Locked || $this === self::Disbursed;
    }
}
