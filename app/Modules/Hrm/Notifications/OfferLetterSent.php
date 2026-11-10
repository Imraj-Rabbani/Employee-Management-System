<?php

namespace App\Modules\Hrm\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To the APPLICANT (on-demand, they have no login): the offer letter as a PDF attachment (6.7). [REC-06]
 *
 * Queued, plain values only: the PDF is made BEFORE queuing (it needs the company database) and carried
 * as base64 text, so the queue worker only has to send it.
 */
class OfferLetterSent extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $companyName,
        public readonly string $applicantName,
        public readonly string $designation,
        public readonly ?string $answerBy,      // formatted date, or null
        public readonly string $fileName,
        public readonly string $pdfBase64,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Offer of employment: :designation at :company', ['designation' => $this->designation, 'company' => $this->companyName]))
            ->greeting(__('Dear :name,', ['name' => $this->applicantName]))
            ->line(__('We are pleased to offer you the position of :designation. Your offer letter is attached.', ['designation' => $this->designation]))
            ->when($this->answerBy, fn (MailMessage $m) => $m->line(__('Please let us know your decision by :date.', ['date' => $this->answerBy])))
            ->line(__('Reply to this email if you have any questions.'))
            ->attachData(base64_decode($this->pdfBase64), $this->fileName, ['mime' => 'application/pdf']);
    }
}
