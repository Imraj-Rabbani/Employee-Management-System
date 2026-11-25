<?php

namespace App\Modules\Core\Livewire;

use App\Models\User;
use App\Modules\Core\Services\NotificationPresenter;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Notifications page (the bell in the top bar opens it): every in-app notification of the logged-in
 * user, newest first. Nothing is deleted, so this is the full history.
 *
 * Clicking one marks it read and opens what it is about. Tick rows → "Mark as read", or "Mark all as read".
 * Filter: all / unread / read.
 */
class NotificationsTable extends Component
{
    use WithPagination;

    /** '' = all, 'unread', 'read' */
    #[Url(as: 'status', except: '')]
    public string $status = '';

    #[Url(as: 'dir', except: 'desc')]
    public string $sortDir = 'desc';

    #[Url(as: 'per_page', except: 25)]
    public int $perPage = 25;

    /** @var list<string> ids of the ticked rows */
    public array $selected = [];

    /** Open one: mark it read, then go to what it is about. */
    public function open(string $id, NotificationPresenter $presenter): void
    {
        $notification = $this->notifications()->findOrFail($id);
        $notification->markAsRead();

        $this->redirect($presenter->link($notification), navigate: true);
    }

    public function markSelectedRead(): void
    {
        $this->notifications()->whereIn('id', $this->selected)->whereNull('read_at')->update(['read_at' => now()]);
        $this->selected = [];
        $this->dispatch('toast', message: __('Marked as read.'), variant: 'success');
    }

    public function markAllRead(): void
    {
        $this->notifications()->whereNull('read_at')->update(['read_at' => now()]);
        $this->selected = [];
        $this->dispatch('toast', message: __('All notifications marked as read.'), variant: 'success');
    }

    /** Newest / oldest first (the only sortable column: Received). */
    public function sort(): void
    {
        $this->sortDir = $this->sortDir === 'desc' ? 'asc' : 'desc';
    }

    public function applyFilters(): void
    {
        $this->resetPage();
        $this->selected = [];
        Flux::modal('notification-filters')->close();
    }

    public function clearFilters(): void
    {
        $this->reset('status');
        $this->applyFilters();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    /** Only the logged-in user's own notifications, ever. */
    private function notifications(): MorphMany
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->notifications();
    }

    /** @return MorphMany<DatabaseNotification, User>|Builder<DatabaseNotification> */
    private function filteredQuery(): MorphMany|Builder
    {
        return $this->notifications()
            ->reorder()
            ->when($this->status === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->when($this->status === 'read', fn ($q) => $q->whereNotNull('read_at'))
            ->orderBy('created_at', $this->sortDir === 'asc' ? 'asc' : 'desc');
    }

    public function render(NotificationPresenter $presenter): View
    {
        $perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 25;

        return view('core::livewire.notifications-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
            'unread' => $this->notifications()->whereNull('read_at')->count(),
            'presenter' => $presenter,
        ]);
    }
}
