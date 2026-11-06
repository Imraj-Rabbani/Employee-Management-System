<?php

namespace App\Modules\Workflow\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To an approver: "a document needs your approval". In-app always, email when the
 * workflow has send_email on. The email has a signed link to approve / reject without logging in.
 *
 * Queued (sent by the queue worker, so the web request stays fast). It only carries plain
 * values, no models, so the worker does not need to switch to the company's database.
 */
class ApprovalNeeded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $approvalId,
        public readonly string $typeName,     // "Leave application"
        public readonly string $subject,      // "Annual leave, 3 days"
        public readonly int $level,
        public readonly ?string $requesterName,
        public readonly string $respondUrl,   // signed, expires
        public readonly bool $sendEmail,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->sendEmail ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Approval needed: :subject', ['subject' => $this->subject]))
            ->line(__(':type ":subject" needs your approval (level :level).', [
                'type' => $this->typeName, 'subject' => $this->subject, 'level' => $this->level,
            ]))
            ->when($this->requesterName, fn (MailMessage $m) => $m->line(__('Requested by :name.', ['name' => $this->requesterName])))
            ->action(__('Approve or reject'), $this->respondUrl)
            ->line(__('This link works for :days days.', ['days' => 7]));
    }

    /**
     * Saved as JSON in the notifications table (the in-app bell, task 3.6).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'approval_needed',
            'approval_id' => $this->approvalId,
            'type' => $this->typeName,
            'subject' => $this->subject,
            'level' => $this->level,
            'requester' => $this->requesterName,
        ];
    }
}
