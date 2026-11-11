<?php

namespace Tests\Feature\Payroll;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Payroll\Models\Roster;
use App\Modules\Payroll\Models\RosterItem;
use App\Modules\Payroll\Models\Shift;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Enums\ApproverType;
use App\Modules\Workflow\Models\Approval;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowStep;
use App\Modules\Workflow\Services\WorkflowEngine;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 7.2a: rosters + roster items, approvable. [TA-03]
 */
class RosterModelsTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->tenant = $this->actingAsTenantUser()->tenant;
    }

    public function test_the_approval_type_is_registered_by_the_migration(): void
    {
        $this->assertSame(Roster::class, ApprovalType::forKey('roster')?->model_class);
    }

    public function test_a_new_roster_is_an_editable_draft_with_its_dates(): void
    {
        $roster = Roster::factory()->create(['from_date' => '2026-12-30', 'to_date' => '2027-01-02']);

        $this->assertTrue($roster->isDraft());
        $this->assertTrue($roster->isEditable());
        $this->assertFalse($roster->isApproved());
        $this->assertSame('RST-'.str_pad((string) $roster->id, 4, '0', STR_PAD_LEFT), $roster->number());
        $this->assertSame(['2026-12-30', '2026-12-31', '2027-01-01', '2027-01-02'],
            array_map(fn (Carbon $d) => $d->toDateString(), $roster->dates()));
    }

    public function test_approval_stamps_approved_at_and_locks_the_roster(): void
    {
        $unit = OrgUnit::factory()->create();
        $boss = Employee::factory()->create(['org_unit_id' => $unit->id]);
        $boss->forceFill(['user_id' => User::factory()->for($this->tenant)->create()->id])->save();
        $planner = Employee::factory()->create(['org_unit_id' => $unit->id, 'supervisor_id' => $boss->id]);
        $planner->forceFill(['user_id' => User::factory()->for($this->tenant)->create()->id])->save();

        $workflow = Workflow::create(['approval_type_id' => ApprovalType::forKey('roster')?->id, 'name' => 'Roster']);
        WorkflowStep::create(['workflow_id' => $workflow->id, 'level' => 1, 'approver_type' => ApproverType::Supervisor]);

        $roster = Roster::factory()->create(['org_unit_id' => $unit->id, 'requested_by_user_id' => $planner->user_id]);
        $roster->submitForApproval(User::findOrFail($planner->user_id));

        $this->assertSame($boss->user_id, Approval::sole()->approver_user_id);   // the planner's supervisor
        $this->assertFalse($roster->fresh()->isEditable());

        app(WorkflowEngine::class)->respond(Approval::sole(), ApprovalStatus::Approved, User::findOrFail($boss->user_id));

        $this->assertTrue($roster->fresh()->isApproved());
        $this->assertFalse($roster->fresh()->isEditable());
    }

    public function test_a_form_cannot_set_approved_at(): void
    {
        $roster = Roster::factory()->create();
        $roster->update(['approved_at' => now()]);

        $this->assertNull($roster->fresh()->approved_at);
    }

    public function test_one_cell_per_employee_and_day(): void
    {
        $item = RosterItem::factory()->create();

        $this->expectException(QueryException::class);
        RosterItem::factory()->create(['roster_id' => $item->roster_id, 'employee_id' => $item->employee_id, 'date' => $item->date]);
    }

    public function test_only_approved_rosters_count_and_the_newest_approval_wins(): void
    {
        $employee = Employee::factory()->create();
        $morning = Shift::factory()->create(['name' => 'Morning']);
        $night = Shift::factory()->overnight()->create();
        $day = Carbon::parse('2026-12-03');

        // A draft does not count.
        $draft = Roster::factory()->create();
        RosterItem::factory()->for($draft)->create(['employee_id' => $employee->id, 'date' => $day, 'shift_id' => $night->id]);
        $this->assertNull(RosterItem::planFor($employee, $day));

        // An approved one does.
        $first = Roster::factory()->approved()->create();
        $planned = RosterItem::factory()->for($first)->create(['employee_id' => $employee->id, 'date' => $day, 'shift_id' => $morning->id]);
        $this->assertTrue(RosterItem::planFor($employee, $day)?->is($planned));
        $this->assertNull(RosterItem::planFor($employee, $day->copy()->addDay()));

        // A later approved roster for the same day replaces it (e.g. a changed plan).
        $this->travel(1)->minutes();
        $change = Roster::factory()->approved()->create();
        $off = RosterItem::factory()->for($change)->off()->create(['employee_id' => $employee->id, 'date' => $day]);

        $plan = RosterItem::planFor($employee->id, $day);
        $this->assertTrue($plan?->is($off));
        $this->assertTrue($plan->is_off);
        $this->assertNull($plan->shift_id);
    }

    public function test_deleting_a_roster_deletes_its_cells_but_a_used_shift_stays(): void
    {
        $item = RosterItem::factory()->create();

        try {
            $item->shift->delete();
            $this->fail('A shift used in a roster should not be deletable.');
        } catch (QueryException $e) {
            $this->assertSame('23000', $e->getCode());
        }

        $item->roster->delete();
        $this->assertModelMissing($item);
    }
}
