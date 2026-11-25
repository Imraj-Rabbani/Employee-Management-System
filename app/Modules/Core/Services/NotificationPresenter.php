<?php

namespace App\Modules\Core\Services;

use Illuminate\Notifications\DatabaseNotification;

/**
 * Turns a stored in-app notification (the "database" channel) into a sentence and a link.
 * Each notification's toArray() stores a 'kind' plus its details; this class decides how it reads.
 * Used by the bell (NotificationBell) and the Notifications page (NotificationsTable).
 *
 *     $presenter->text($notification)   'Leave "Rahim, 3 days" needs your approval.'
 *     $presenter->link($notification)   where clicking it goes
 */
class NotificationPresenter
{
    public function text(DatabaseNotification $notification): string
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;
        $replace = ['type' => $data['type'] ?? '', 'subject' => $data['subject'] ?? ''];

        return match ($data['kind'] ?? null) {
            'approval_needed' => __(':type ":subject" needs your approval.', $replace),
            'approval_decided' => match ($data['status'] ?? null) {
                'approved' => __(':type ":subject" has been approved.', $replace),
                'rejected' => __(':type ":subject" was rejected.', $replace),
                default => __(':type ":subject" was sent back to you.', $replace),
            },
            'interview_scheduled' => ($data['changed'] ?? false)
                ? __('Interview changed: :name, round :round, :when.', ['name' => $data['applicant'] ?? '', 'round' => $data['round'] ?? '', 'when' => $data['when'] ?? ''])
                : __('You are on the interview panel for :name, round :round, :when.', ['name' => $data['applicant'] ?? '', 'round' => $data['round'] ?? '', 'when' => $data['when'] ?? '']),
            default => (string) ($data['message'] ?? __('Notification')),
        };
    }

    public function link(DatabaseNotification $notification): string
    {
        return match ($notification->data['kind'] ?? null) {
            'approval_needed' => route('workflow.inbox'),
            'interview_scheduled' => route('ess.interviews'),
            default => route('home'),
        };
    }
}
