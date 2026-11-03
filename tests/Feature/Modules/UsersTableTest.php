<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use App\Modules\Core\Livewire\UsersTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tests the DataTable base (search, sort, filters, pagination, columns, bulk actions, export)
 * through its first real screen: Setup → Users.
 */
class UsersTableTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();

        $this->me = User::factory()->create(['name' => 'Admin Person', 'email' => 'admin@example.com']);
        $this->actingAs($this->me);
    }

    public function test_users_page_renders_the_table(): void
    {
        $this->get('/core/users')
            ->assertOk()
            ->assertSeeLivewire(UsersTable::class)
            ->assertSee('admin@example.com');
    }

    public function test_search_matches_name_or_email(): void
    {
        User::factory()->create(['name' => 'Rahim Uddin', 'email' => 'rahim@example.com']);
        User::factory()->create(['name' => 'Karim Ali', 'email' => 'karim@test.org']);

        Livewire::test(UsersTable::class)
            ->set('search', 'rahim')
            ->assertSee('Rahim Uddin')
            ->assertDontSee('Karim Ali')
            ->set('search', 'test.org')
            ->assertSee('Karim Ali')
            ->assertDontSee('Rahim Uddin');
    }

    public function test_search_treats_percent_as_text_not_a_wildcard(): void
    {
        User::factory()->create(['name' => 'Rahim Uddin']);

        Livewire::test(UsersTable::class)
            ->set('search', '%')
            ->assertDontSee('Rahim Uddin');
    }

    public function test_sorting_toggles_direction_and_ignores_unknown_fields(): void
    {
        User::factory()->create(['name' => 'Zara Khan']);
        User::factory()->create(['name' => 'Anika Das']);

        Livewire::test(UsersTable::class)
            ->call('sort', 'name')
            ->assertSet('sortDir', 'asc')
            ->assertSeeInOrder(['Admin Person', 'Anika Das', 'Zara Khan'])
            ->call('sort', 'name')
            ->assertSet('sortDir', 'desc')
            ->assertSeeInOrder(['Zara Khan', 'Anika Das', 'Admin Person'])
            ->call('sort', 'password')
            ->assertSet('sortBy', 'name');
    }

    public function test_status_filter(): void
    {
        User::factory()->unverified()->create(['name' => 'Pending Person']);

        Livewire::test(UsersTable::class)
            ->set('filters.verified', 'no')
            ->assertSee('Pending Person')
            ->assertDontSee('Admin Person')
            ->set('filters.verified', 'yes')
            ->assertSee('Admin Person')
            ->assertDontSee('Pending Person')
            ->call('clearFilters')
            ->assertSee('Pending Person')
            ->assertSee('Admin Person');
    }

    public function test_pagination_and_page_size(): void
    {
        User::factory()->count(14)->create();

        Livewire::test(UsersTable::class)
            ->assertSee('Showing')
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 10 && $rows->total() === 15)
            ->set('perPage', 25)
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 15)
            ->set('perPage', 9999)
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 10);
    }

    public function test_columns_can_be_hidden_except_always_visible_ones(): void
    {
        Livewire::test(UsersTable::class)
            ->assertSee('admin@example.com')
            ->call('toggleColumn', 'email')
            ->assertDontSee('admin@example.com')
            ->call('toggleColumn', 'name')
            ->assertSee('Admin Person')
            ->call('toggleColumn', 'email')
            ->assertSee('admin@example.com');
    }

    public function test_status_column_shows_a_badge(): void
    {
        Livewire::test(UsersTable::class)
            ->assertSeeHtml('rounded-full')
            ->assertSee('Verified')
            ->assertDontSeeHtml('&lt;span');
    }

    public function test_select_page_selects_visible_rows(): void
    {
        User::factory()->count(2)->create();

        Livewire::test(UsersTable::class)
            ->set('selectPage', true)
            ->assertCount('selected', 3)
            ->assertSee('3 selected')
            ->call('clearSelection')
            ->assertCount('selected', 0);
    }

    public function test_bulk_verify(): void
    {
        $pending = User::factory()->unverified()->create();

        Livewire::test(UsersTable::class)
            ->set('selected', [(string) $pending->id])
            ->call('runBulkAction', 'verify')
            ->assertDispatched('toast')
            ->assertCount('selected', 0);

        $this->assertNotNull($pending->fresh()->email_verified_at);
    }

    public function test_bulk_delete_never_deletes_yourself(): void
    {
        $other = User::factory()->create();

        Livewire::test(UsersTable::class)
            ->set('selected', [(string) $other->id, (string) $this->me->id])
            ->call('runBulkAction', 'delete');

        $this->assertModelMissing($other);
        $this->assertModelExists($this->me);
    }

    public function test_unknown_bulk_action_is_forbidden(): void
    {
        Livewire::test(UsersTable::class)
            ->call('runBulkAction', 'render')
            ->assertForbidden();
    }

    public function test_export_downloads_an_excel_file(): void
    {
        Livewire::test(UsersTable::class)
            ->call('export')
            ->assertFileDownloaded('users-table-'.now()->format('Y-m-d').'.xlsx');
    }

    public function test_search_is_kept_in_the_url(): void
    {
        User::factory()->create(['name' => 'Rahim Uddin']);

        Livewire::withQueryParams(['q' => 'rahim'])
            ->test(UsersTable::class)
            ->assertSet('search', 'rahim')
            ->assertDontSee('Admin Person');
    }
}
