<?php

namespace App\Modules\Workflow\Enums;

/**
 * Status of one approval row, and of a document as a whole (HasApprovals::approvalStatus()).
 * The ->value is what is stored in approvals.status.
 *
 *     ApprovalStatus::Pending->label()   "Pending"   (translated)
 *     ApprovalStatus::from('approved')   string from the DB → enum
 */
enum ApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Review = 'review';     // sent back to the requester for changes

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Approved => __('Approved'),
            self::Rejected => __('Rejected'),
            self::Review => __('Sent back'),
        };
    }

    /** Colour for <x-ui.badge>. */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Review => 'info',
        };
    }

    /** Has someone answered (approved, rejected or sent back)? */
    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
