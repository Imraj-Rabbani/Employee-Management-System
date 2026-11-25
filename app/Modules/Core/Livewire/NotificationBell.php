<?php

namespace App\Modules\Core\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The bell in the top bar. Shows how many notifications are unread (checked every minute: wire:poll
 * in the view); clicking it opens the Notifications page with the whole history (NotificationsTable).
 */
class NotificationBell extends Component
{
    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('core::livewire.notification-bell', [
            'unread' => $user->unreadNotifications()->count(),
        ]);
    }
}
