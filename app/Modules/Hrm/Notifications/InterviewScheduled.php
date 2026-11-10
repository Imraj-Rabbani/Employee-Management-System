<?php

namespace App\Modules\Hrm\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To an interviewer: "you are on the panel of an interview" (6.6). [REC-05]
 * Sent when an interview is scheduled, and again ($changed) when its time / place changes.
 *
 * Queued, plain values only (like the Workflow notifications), so the worker needs no company database.
 * A panelist with a login also gets it in the bell (database); one without a login only by email.
 */
class InterviewScheduled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $applicantName,
        public readonly string $postingTitle,   // "JOB-0003 · Sales Officer"
        public readonly int $round,
        public readonly string $when,           // already formatted in the company's date format / timezone
        public readonly ?string $location,
        public readonly ?string $notes,
        public readonly ?string $url,           // My Interviews; null when the panelist has no login
        public readonly bool $changed = false,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        // On-demand (email only) notifications are AnonymousNotifiable: they have no database rows.
        return method_exists($notifiable, 'notifications') ? ['mail', 'database'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->changed
            ? __('Interview changed: :name, round :round', ['name' => $this->applicantName, 'round' => $this->round])
            : __('Interview: :name, round :round', ['name' => $this->applicantName, 'round' => $this->round]);

        return (new MailMessage)
            ->subject($subject)
            ->line($this->changed
                ? __('An interview you are on the panel of has changed.')
                : __('You are on the interview panel for :name (:posting).', ['name' => $this->applicantName, 'posting' => $this->postingTitle]))
            ->line(__('Round :round · :when', ['round' => $this->round, 'when' => $this->when]))
            ->when($this->location, fn (MailMessage $m) => $m->line(__('Where: :place', ['place' => $this->location])))
            ->when($this->notes, fn (MailMessage $m) => $m->line(__('Note from HR: :note', ['note' => $this->notes])))
            ->when($this->url, fn (MailMessage $m) => $m->action(__('Open the score sheet'), (string) $this->url));
    }

    /**
     * The in-app bell (NotificationBell turns 'kind' into a sentence and a link).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'interview_scheduled',
            'applicant' => $this->applicantName,
            'round' => $this->round,
            'when' => $this->when,
            'changed' => $this->changed,
        ];
    }
}
