<?php

namespace App\Modules\Core\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Component;

/**
 * The bell in the top bar: the logged-in user's in-app notifications (the "database" channel).
 * Checks for new ones every minute (wire:poll in the view).
 *
 * Each notification's toArray() decides what it says; this component turns the stored
 * 'kind' into a sentence and a link.
 */
class NotificationBell extends Component
{
    public function open(string $id): void
    {
        $notification = $this->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $this->redirect($this->linkFor($notification), navigate: true);
    }

    public function markAllRead(): void
    {
        $this->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    /** One line of text for a stored notification. */
    public function textFor(DatabaseNotification $notification): string
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
            default => (string) ($data['message'] ?? __('Notification')),
        };
    }

    private function linkFor(DatabaseNotification $notification): string
    {
        return match ($notification->data['kind'] ?? null) {
            'approval_needed' => route('workflow.inbox'),
            default => route('home'),
        };
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        return view('core::livewire.notification-bell', [
            'unread' => $this->user()->unreadNotifications()->count(),
            'latest' => $this->user()->notifications()->latest()->limit(8)->get(),
        ]);
    }
}
