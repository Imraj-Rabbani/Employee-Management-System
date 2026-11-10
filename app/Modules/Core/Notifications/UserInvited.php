<?php

namespace App\Modules\Core\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "You have been given a login: set your password." (5.7) [EMP-07]
 * With register: true, "You are invited to join: complete your registration." (HRM → Employees → Invite user):
 * the person has no employee record yet and fills in their own details.
 * Queued, plain values only (like the Workflow notifications), so the worker needs no company database.
 */
class UserInvited extends Notification implements ShouldQueue
{
    use Queueable;

    public const DAYS_VALID = 7;

    public function __construct(
        public readonly string $companyName,
        public readonly ?string $inviterName,
        public readonly string $url,           // signed, expires after DAYS_VALID
        public readonly bool $register = false,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->register) {
            return (new MailMessage)
                ->subject(__('You are invited to join :company', ['company' => $this->companyName]))
                ->greeting(__('Hello,'))
                ->line(__(':company invites you to the HR system.', ['company' => $this->companyName]))
                ->when($this->inviterName, fn (MailMessage $m) => $m->line(__('Invited by :name.', ['name' => $this->inviterName])))
                ->line(__('Open the link, fill in your details and choose a password.'))
                ->action(__('Complete your registration'), $this->url)
                ->line(__('This link works for :days days and only once.', ['days' => self::DAYS_VALID]));
        }

        return (new MailMessage)
            ->subject(__('Your login for :company', ['company' => $this->companyName]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name ?? '']))
            ->line(__(':company has created a login for you in the HR system.', ['company' => $this->companyName]))
            ->when($this->inviterName, fn (MailMessage $m) => $m->line(__('Invited by :name.', ['name' => $this->inviterName])))
            ->line(__('There you can see your profile, payslips and leave, and send requests.'))
            ->action(__('Set your password'), $this->url)
            ->line(__('This link works for :days days and only once.', ['days' => self::DAYS_VALID]));
    }
}
