<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Livewire\CurrencyForm;
use App\Modules\Core\Livewire\RoleForm;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Livewire\Organization\DesignationForm;
use App\Modules\Hrm\Livewire\Organization\DesignationTable;
use App\Modules\Hrm\Livewire\Recruitment\ManpowerRequestForm;
use App\Modules\Hrm\Livewire\Recruitment\ManpowerRequestTable;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\ManpowerRequest;
use App\Modules\Payroll\Livewire\Attendance\RosterTable;
use App\Modules\Payroll\Models\Roster;
use App\Modules\Payroll\Models\RosterItem;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Enums\ApproverType;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * The list toolbar: Add new / Edit / Delete / Export / Request approval on the left, Filter on the right,
 * tick boxes on the rows. Designations stand in for every LookupForm list.
 */
class ListToolbarTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->tenant = $this->actingAsTenantUser()->tenant;   // Administrator
    }

    // ---------------------------------------------------------------- the toolbar and the Filter form

    public function test_the_toolbar_has_the_actions_and_a_filter_button_instead_of_a_search_box(): void
    {
        $officer = Designation::factory()->create(['name' => 'Officer']);

        Livewire::test(DesignationTable::class)
            ->assertSee(['Add new', 'Edit', 'Delete', 'Export', 'Filter'])
            ->assertDontSee('Request approval')                                  // not an approvable list
            ->assertSeeHtml('wire:model="selected" value="'.$officer->id.'"')   // a tick box per row
            ->assertDontSeeHtml('wire:model.live.debounce.300ms="search"');      // no search box in the toolbar

        $this->get(route('hrm.org.designations'))->assertOk()->assertSee('Add new');
    }

    public function test_filters_change_the_list_only_on_apply_and_the_button_counts_them(): void
    {
        Designation::factory()->create(['name' => 'Officer']);
        Designation::factory()->create(['name' => 'Manager']);

        Livewire::test(DesignationTable::class)
            ->set('selected', ['1'])
            ->set('search', 'Offi')
            ->call('applyFilters')
            ->assertSee('Officer')->assertDontSee('Manager')
            ->assertSee('Filter (1)')
            ->assertSet('selected', [])        // filtering forgets the ticked rows (they may be hidden now)
            ->call('clearFilters')
            ->assertSee('Manager')
            ->assertDontSee('Filter (1)');
    }

    // ---------------------------------------------------------------- Delete: many at once, rules per row

    public function test_delete_removes_the_ticked_rows_that_are_free_and_keeps_those_in_use(): void
    {
        $free = Designation::factory()->create(['name' => 'Free']);
        $used = Designation::factory()->create(['name' => 'Used']);
        Employee::factory()->create(['designation_id' => $used->id]);

        Livewire::test(DesignationForm::class)
            ->dispatch('delete-designation', ids: [(string) $free->id, (string) $used->id])
            ->assertDispatched('designation-saved')
            ->assertDispatched('toast', fn ($name, $params) => $params['variant'] === 'warning'
                && str_contains($params['message'], '1 deleted.') && str_contains($params['message'], 'Used: This record is still in use'));

        $this->assertFalse(Designation::whereKey($free->id)->exists());
        $this->assertTrue(Designation::whereKey($used->id)->exists());
    }

    public function test_the_tables_forget_the_ticked_rows_after_a_delete(): void
    {
        Livewire::test(DesignationTable::class)
            ->set('selected', ['5', '6'])
            ->dispatch('designation-saved')
            ->assertSet('selected', []);
    }

    public function test_per_form_delete_rules_also_hold_for_many_rows(): void
    {
        // The Administrator role and the default currency never go.
        $spare = Role::create(['name' => 'Spare']);
        Livewire::test(RoleForm::class)->dispatch('delete-role', ids: [Role::administrator()?->id, $spare->id]);
        $this->assertNotNull(Role::administrator());
        $this->assertNull(Role::find($spare->id));

        Livewire::test(CurrencyForm::class)->dispatch('delete-currency', ids: [Currency::default()?->id])
            ->assertDispatched('toast', fn ($name, $params) => $params['variant'] === 'error');
        $this->assertNotNull(Currency::default());
    }

    // ---------------------------------------------------------------- Request approval

    public function test_manpower_requests_ticked_drafts_are_sent_and_the_rest_explained(): void
    {
        $this->workflow('manpower_request');
        $draft = ManpowerRequest::factory()->create();
        $sent = ManpowerRequest::factory()->create();
        $sent->submitForApproval(User::factory()->for($this->tenant)->create());

        Livewire::test(ManpowerRequestTable::class)
            ->assertSee('Request approval')
            ->set('selected', [(string) $draft->id, (string) $sent->id])
            ->call('requestApproval')
            ->assertSet('selected', [])
            ->assertDispatched('toast', fn ($name, $params) => str_contains($params['message'], '1 sent for approval.')
                && str_contains($params['message'], $sent->number().': This request is already waiting'));

        $this->assertSame(ApprovalStatus::Pending, $draft->fresh()?->approvalStatus());

        // Delete from the list: only drafts go.
        $other = ManpowerRequest::factory()->create();
        Livewire::test(ManpowerRequestForm::class)->dispatch('delete-manpower-request', ids: [$draft->id, $other->id]);
        $this->assertTrue(ManpowerRequest::whereKey($draft->id)->exists());
        $this->assertFalse(ManpowerRequest::whereKey($other->id)->exists());
    }

    public function test_a_roster_without_cells_is_not_sent(): void
    {
        $this->workflow('roster');
        $empty = Roster::factory()->create();
        $planned = Roster::factory()->create();
        RosterItem::factory()->for($planned)->create();

        Livewire::test(RosterTable::class)
            ->set('selected', [(string) $empty->id, (string) $planned->id])
            ->call('requestApproval')
            ->assertDispatched('toast', fn ($name, $params) => $params['variant'] === 'warning'
                && str_contains($params['message'], 'Plan at least one cell'));

        $this->assertNull($empty->fresh()?->approvalStatus());
        $this->assertSame(ApprovalStatus::Pending, $planned->fresh()?->approvalStatus());
    }

    /** A one-level workflow answered by a second Administrator (never the requester). */
    private function workflow(string $type): void
    {
        $workflow = Workflow::create(['approval_type_id' => ApprovalType::forKey($type)?->id, 'name' => 'Test']);
        WorkflowStep::create(['workflow_id' => $workflow->id, 'level' => 1, 'approver_type' => ApproverType::Role, 'approver_role_id' => Role::administrator()?->id]);
        Role::administrator()?->assignTo(User::factory()->for($this->tenant)->create());
    }
}
