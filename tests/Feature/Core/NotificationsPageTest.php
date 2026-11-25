<?php

namespace Tests\Feature\Core;

use App\Models\User;
use App\Modules\Core\Livewire\NotificationsTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * The Notifications page: the bell opens it; it keeps the whole history (read ones too).
 */
class NotificationsPageTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->actingAsTenantUser();
    }

    private function notify(User $user, string $message, bool $read = false, int $daysAgo = 0): DatabaseNotification
    {
        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'data' => ['message' => $message],
            'read_at' => $read ? now() : null,
        ]);
        $notification->forceFill(['created_at' => now()->subDays($daysAgo)])->save();

        return $notification;
    }

    public function test_the_bell_links_to_the_page_which_lists_read_and_unread_newest_first(): void
    {
        $this->notify($this->user, 'Old and read', read: true, daysAgo: 5);
        $this->notify($this->user, 'New and unread');

        $this->get('/dashboard')->assertSee(route('core.notifications'));

        $this->get(route('core.notifications'))
            ->assertOk()
            ->assertSeeInOrder(['New and unread', 'Old and read'])
            ->assertSee('1 unread');
    }

    public function test_only_your_own_notifications_are_shown(): void
    {
        $other = User::factory()->for($this->user->tenant)->create();
        $this->notify($other, 'Someone else’s');
        $mine = $this->notify($this->user, 'Mine');

        $this->get(route('core.notifications'))->assertSee('Mine')->assertDontSee('Someone else’s');

        $theirs = $other->notifications()->value('id');
        Livewire::test(NotificationsTable::class)->set('selected', [$theirs])->call('markSelectedRead');
        $this->assertNull($other->notifications()->sole()->read_at);
        $this->assertNull($mine->refresh()->read_at);
    }

    public function test_the_filter_shows_unread_or_read(): void
    {
        $this->notify($this->user, 'Read one', read: true);
        $this->notify($this->user, 'Unread one');

        Livewire::test(NotificationsTable::class)
            ->set('status', 'unread')->call('applyFilters')
            ->assertSee('Unread one')->assertDontSee('Read one')
            ->set('status', 'read')->call('applyFilters')
            ->assertSee('Read one')->assertDontSee('Unread one');
    }

    public function test_ticked_ones_or_all_can_be_marked_read_and_stay_in_the_history(): void
    {
        $a = $this->notify($this->user, 'A');
        $this->notify($this->user, 'B');
        $this->notify($this->user, 'C');

        Livewire::test(NotificationsTable::class)->set('selected', [$a->id])->call('markSelectedRead')->assertSet('selected', []);
        $this->assertSame(2, $this->user->unreadNotifications()->count());

        Livewire::test(NotificationsTable::class)->call('markAllRead');
        $this->assertSame(0, $this->user->unreadNotifications()->count());
        $this->assertSame(3, $this->user->notifications()->count(), 'Nothing is deleted.');
    }
}
