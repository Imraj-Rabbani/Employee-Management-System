<?php

namespace Tests\Feature\Core;

use App\Core\Tenancy\TenantManager;
use App\Modules\Core\Livewire\History;
use App\Modules\Core\Livewire\RoleForm;
use App\Modules\Core\Models\Activity;
use App\Modules\Core\Models\FiscalYear;
use App\Modules\Core\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 2.10: every change to a TenantModel is logged (who, when, old → new), and the History tab shows it. [FND-06]
 */
class ActivityLogTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    public function test_the_log_lives_in_the_company_database_not_the_central_one(): void
    {
        $this->actingAsTenantUser();

        $this->assertTrue(Schema::connection('tenant')->hasTable('activity_log'));
        $this->assertFalse(Schema::connection('central')->hasTable('activity_log'));
    }

    public function test_creating_a_record_is_logged_with_who_did_it(): void
    {
        $me = $this->actingAsTenantUser();

        $year = $this->year();

        $entry = Activity::where('subject_type', $year->getMorphClass())->where('subject_id', $year->id)->sole();
        $this->assertSame('created', $entry->event);
        $this->assertTrue($me->is($entry->causer));
        $this->assertSame('FY 2025-26', $entry->attribute_changes?->get('attributes')['name']);
    }

    public function test_an_update_logs_only_the_changed_fields_old_and_new(): void
    {
        $this->actingAsTenantUser();
        $year = $this->year();

        $year->update(['name' => 'Fiscal 2025-26', 'start_date' => '2025-07-01']);   // start_date did not change

        $entry = Activity::where('event', 'updated')->sole();
        $this->assertSame(['name' => 'FY 2025-26'], $entry->attribute_changes?->get('old'));
        $this->assertSame(['name' => 'Fiscal 2025-26'], $entry->attribute_changes?->get('attributes'));
    }

    public function test_a_save_that_changes_nothing_writes_no_entry(): void
    {
        $this->actingAsTenantUser();
        $year = $this->year();

        $year->update(['name' => 'FY 2025-26']);

        $this->assertSame(0, Activity::where('event', 'updated')->count());
    }

    public function test_deleting_is_logged(): void
    {
        $this->actingAsTenantUser();
        $role = Role::create(['name' => 'Temp']);

        $role->delete();

        $this->assertSame(1, Activity::where('event', 'deleted')->where('subject_id', $role->id)->count());
    }

    public function test_changing_a_roles_permissions_writes_one_readable_entry(): void
    {
        $this->actingAsTenantUser();
        $role = Role::create(['name' => 'HR Officer']);
        $before = Activity::count();

        $role->syncPermissions(['hrm.employee.view', 'hrm.employee.create']);

        $this->assertSame($before + 1, Activity::count(), 'One entry, not one per permission row.');
        $entry = Activity::latest('id')->firstOrFail();
        $this->assertSame([], $entry->attribute_changes?->get('old')['permissions']);
        $this->assertSame(['hrm.employee.create', 'hrm.employee.view'], $entry->attribute_changes?->get('attributes')['permissions']);
    }

    public function test_each_company_has_its_own_log(): void
    {
        $this->actingAsTenantUser();
        $this->year();
        $first = tenant();

        $yearEntries = fn () => Activity::where('subject_type', (new FiscalYear)->getMorphClass())->count();

        $other = $this->createMigratedTenant();
        app(TenantManager::class)->switchTo($other);
        $this->assertSame(0, $yearEntries());

        app(TenantManager::class)->switchTo($first);
        $this->assertSame(1, $yearEntries());
    }

    // ---------------------------------------------------------------- History component

    public function test_history_shows_who_changed_what_newest_first(): void
    {
        $this->actingAsTenantUser(['name' => 'Rahim Uddin']);
        $year = $this->year();
        $year->update(['name' => 'Fiscal 2025-26']);

        Livewire::test(History::class, ['subject' => $year, 'can' => 'core.fiscal_years.manage'])
            ->assertSeeInOrder(['Rahim Uddin', 'changed this', 'FY 2025-26', 'Fiscal 2025-26', 'Rahim Uddin', 'created this']);
    }

    public function test_history_says_when_there_is_nothing_yet(): void
    {
        $this->actingAsTenantUser();
        $role = Role::administrator();
        Activity::query()->delete();   // seeding the role was logged; start from an empty log

        Livewire::test(History::class, ['subject' => $role])->assertSee('No history yet');
    }

    public function test_history_shows_ten_entries_then_more_on_request(): void
    {
        $this->actingAsTenantUser();
        $year = $this->year();
        foreach (range(1, 11) as $i) {
            $year->update(['name' => "Name {$i}"]);
        }

        Livewire::test(History::class, ['subject' => $year])
            ->assertSee('Show more')
            ->assertDontSee('created this')
            ->call('showMore')
            ->assertSee('created this')
            ->assertDontSee('Show more');
    }

    public function test_history_needs_the_given_permission(): void
    {
        $this->actingAsTenantUser(permissions: []);
        $year = $this->year();

        Livewire::test(History::class, ['subject' => $year, 'can' => 'core.fiscal_years.manage'])->assertForbidden();
    }

    public function test_the_edit_form_has_a_history_tab(): void
    {
        $this->actingAsTenantUser();
        $role = Role::create(['name' => 'HR Officer']);

        Livewire::test(RoleForm::class)
            ->dispatch('edit-role', id: $role->id)
            ->assertSee('History')
            ->assertSeeLivewire(History::class);

        Livewire::test(RoleForm::class)
            ->dispatch('create-role')
            ->assertDontSeeLivewire(History::class);
    }

    private function year(): FiscalYear
    {
        return FiscalYear::create(['name' => 'FY 2025-26', 'start_date' => '2025-07-01', 'end_date' => '2026-06-30']);
    }
}
