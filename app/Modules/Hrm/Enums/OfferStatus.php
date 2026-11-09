<?php

namespace App\Modules\Hrm\Enums;

/**
 * Where a job offer is (6.7). NOT stored: JobOffer::status() works it out. [REC-06]
 *
 *     draft → pending approval → approved → letter sent → accepted / declined
 *                             ↘ sent back (edit, send again)   ↘ rejected (by the approver: dead)
 */
enum OfferStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending';
    case SentBack = 'review';
    case Rejected = 'rejected';
    case Approved = 'approved';
    case LetterSent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::PendingApproval => __('Waiting for approval'),
            self::SentBack => __('Sent back for changes'),
            self::Rejected => __('Not approved'),
            self::Approved => __('Approved'),
            self::LetterSent => __('Letter sent'),
            self::Accepted => __('Accepted'),
            self::Declined => __('Declined'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::PendingApproval, self::SentBack => 'warning',
            self::Approved, self::LetterSent => 'info',
            self::Accepted => 'success',
            self::Rejected, self::Declined => 'danger',
        };
    }

    /** Over: a new offer may be made for the application. */
    public function isDead(): bool
    {
        return $this === self::Rejected || $this === self::Declined;
    }
}
