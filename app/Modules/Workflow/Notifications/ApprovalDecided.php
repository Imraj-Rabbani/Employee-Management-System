<?php

namespace App\Modules\Workflow\Notifications;

use App\Modules\Workflow\Enums\ApprovalStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To the requester: your document was approved, rejected or sent back.
 * Queued and model-free, like ApprovalNeeded.
 */
class ApprovalDecided extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $approvalId,
        public readonly string $typeName,
        public readonly string $subject,
        public readonly ApprovalStatus $status,   // Approved, Rejected or Review
        public readonly ?string $responderName,
        public readonly ?string $comment,
        public readonly bool $sendEmail,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->sendEmail ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__(':status: :subject', ['status' => $this->status->label(), 'subject' => $this->subject]))
            ->line($this->sentence());

        if ($this->comment !== null) {
            $message->line(__('Comment: :comment', ['comment' => $this->comment]));
        }

        if ($this->status === ApprovalStatus::Review) {
            $message->line(__('Please make the changes and submit it again.'));
        }

        return $message;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'approval_decided',
            'approval_id' => $this->approvalId,
            'type' => $this->typeName,
            'subject' => $this->subject,
            'status' => $this->status->value,
            'responder' => $this->responderName,
            'comment' => $this->comment,
        ];
    }

    private function sentence(): string
    {
        $replace = ['type' => $this->typeName, 'subject' => $this->subject, 'name' => $this->responderName ?? '-'];

        return match ($this->status) {
            ApprovalStatus::Approved => __(':type ":subject" has been approved.', $replace),
            ApprovalStatus::Rejected => __(':type ":subject" was rejected by :name.', $replace),
            default => __(':type ":subject" was sent back to you by :name.', $replace),
        };
    }
}
