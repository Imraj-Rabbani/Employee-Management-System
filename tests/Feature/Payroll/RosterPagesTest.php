<?php

namespace Tests\Feature\Payroll;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Payroll\Livewire\Attendance\RosterForm;
use App\Modules\Payroll\Models\Roster;
use App\Modules\Payroll\Models\RosterItem;
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
 * Task 7.2b: Payroll → Attendance → Rosters: list, roster page, New / Edit / Delete. [TA-03]
 */
class RosterPagesTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private OrgUnit $factory;

    private OrgUnit $office;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createMigratedTenant();
        $this->actingAsTenantUser(tenant: $this->tenant);   // Administrator

        $this->factory = OrgUnit::factory()->create(['name' => 'Factory']);
        $this->office = OrgUnit::factory()->create(['name' => 'Office']);
    }

    /** A user who may plan rosters, limited to the Factory unit. */
    private function factoryPlanner(): User
    {
        $user = $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['payroll.roster.manage']);
        Role::where('name', 'like', 'Test role%')->latest('id')->firstOrFail()->update(['org_unit_id' => $this->factory->id]);

        return $user;
    }

    public function test_the_list_and_page_need_the_permission_and_show_only_visible_units(): void
    {
        $mine = Roster::factory()->create(['org_unit_id' => $this->factory->id, 'title' => 'Factory December']);
        $other = Roster::factory()->create(['org_unit_id' => $this->office->id, 'title' => 'Office December']);

        $this->factoryPlanner();
        $this->get('/payroll/attendance')->assertRedirect(route('payroll.attendance.rosters'));   // Rosters = first tab
        $this->get('/payroll/attendance/rosters')->assertOk()->assertSee('Factory December')->assertDontSee('Office December');
        $this->get('/payroll/attendance/rosters/'.$mine->id)->assertOk()->assertSee($mine->number());
        $this->get('/payroll/attendance/rosters/'.$other->id)->assertNotFound();
        $this->get('/payroll/attendance/shifts')->assertForbidden();   // setup is another permission

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['payroll.attendance.setup']);
        $this->get('/payroll/attendance/rosters')->assertForbidden();
        $this->get('/payroll/attendance')->assertRedirect(route('payroll.attendance.shifts'));
    }

    public function test_a_new_roster_starts_as_next_month_and_opens_after_saving(): void
    {
        $this->travelTo('2026-11-10');
        $this->factoryPlanner();

        $component = Livewire::test(RosterForm::class)
            ->dispatch('create-roster')
            ->assertSet('form.org_unit_id', (string) $this->factory->id)   // the only unit this user sees
            ->assertSet('form.title', 'December 2026')
            ->assertSet('form.from_date', '2026-12-01')
            ->assertSet('form.to_date', '2026-12-31')
            ->call('save')
            ->assertHasNoErrors();

        $roster = Roster::sole();
        $component->assertRedirect(route('payroll.attendance.rosters.show', $roster->id));
        $this->assertSame(auth()->id(), $roster->requested_by_user_id);
        $this->assertTrue($roster->isDraft());
    }

    public function test_roster_rules(): void
    {
        $this->factoryPlanner();

        Livewire::test(RosterForm::class)
            ->dispatch('create-roster')
            ->set('form.org_unit_id', (string) $this->office->id)       // not visible to this user
            ->set('form.from_date', '2026-12-10')
            ->set('form.to_date', '2026-12-01')                          // before the start
            ->call('save')
            ->assertHasErrors(['form.org_unit_id', 'form.to_date'])
            ->set('form.org_unit_id', (string) $this->factory->id)
            ->set('form.to_date', '2027-02-10')                          // 63 days
            ->call('save')
            ->assertHasErrors(['form.to_date'])
            ->set('form.to_date', '2027-02-09')                          // 62 days
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_shortening_the_period_removes_cells_outside_it_and_the_unit_is_fixed_once_cells_exist(): void
    {
        $roster = Roster::factory()->create(['org_unit_id' => $this->factory->id, 'from_date' => '2026-12-01', 'to_date' => '2026-12-07']);
        $kept = RosterItem::factory()->for($roster)->create(['date' => '2026-12-02']);
        $dropped = RosterItem::factory()->for($roster)->create(['date' => '2026-12-06']);

        Livewire::test(RosterForm::class)
            ->dispatch('edit-roster', id: $roster->id)
            ->set('form.org_unit_id', (string) $this->office->id)
            ->call('save')
            ->assertHasErrors(['form.org_unit_id'])
            ->set('form.org_unit_id', (string) $this->factory->id)
            ->set('form.to_date', '2026-12-05')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertModelExists($kept);
        $this->assertModelMissing($dropped);
    }

    public function test_only_a_draft_can_be_deleted_and_a_sent_roster_not_edited(): void
    {
        Notification::fake();
        $draft = Roster::factory()->create(['org_unit_id' => $this->factory->id]);
        RosterItem::factory()->for($draft)->create();

        Livewire::test(RosterForm::class)
            ->dispatch('edit-roster', id: $draft->id)
            ->call('delete')
            ->assertRedirect(route('payroll.attendance.rosters'));
        $this->assertModelMissing($draft);
        $this->assertSame(0, RosterItem::count());

        // Sent for approval through a real one-level workflow (approver: the Administrator role).
        $workflow = Workflow::create(['approval_type_id' => ApprovalType::forKey('roster')?->id, 'name' => 'Roster']);
        WorkflowStep::create(['workflow_id' => $workflow->id, 'level' => 1, 'approver_type' => ApproverType::Role, 'approver_role_id' => Role::administrator()?->id]);
        Role::administrator()?->assignTo(User::factory()->for($this->tenant)->create());
        $sent = Roster::factory()->create(['org_unit_id' => $this->factory->id]);
        $sent->submitForApproval(auth()->user());
        $this->assertFalse($sent->fresh()->isEditable());

        Livewire::test(RosterForm::class)
            ->dispatch('edit-roster', id: $sent->id)
            ->set('form.title', 'Changed')
            ->call('save')
            ->assertHasErrors(['form.title'])
            ->call('delete')
            ->assertHasErrors(['form.title']);
        $this->assertModelExists($sent);
        $this->assertNotSame('Changed', $sent->fresh()->title);
    }
}
