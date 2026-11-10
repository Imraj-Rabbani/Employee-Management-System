<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Modules\Core\Services\Permissions;
use App\Modules\Hrm\Actions\ChangeOrgUnit;
use App\Modules\Hrm\Livewire\Employees\OrgHistory;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeOrgAssignment;
use App\Modules\Hrm\Models\OrgUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 5.4: effective-dated org unit history (HasEffectiveDates, ChangeOrgUnit, History tab). [EMP-04]
 *
 * Rahim joined Finance on 2024-01-01. Units: Head Office › Finance, Head Office › Sales.
 */
class OrgAssignmentTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private OrgUnit $finance;

    private OrgUnit $sales;

    private Employee $rahim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->actingAsTenantUser()->tenant;

        $head = OrgUnit::factory()->create(['name' => 'Head Office']);
        $this->finance = OrgUnit::factory()->childOf($head)->create(['name' => 'Finance']);
        $this->sales = OrgUnit::factory()->childOf($head)->create(['name' => 'Sales']);

        $this->rahim = Employee::factory()->inUnit($this->finance)->create([
            'first_name' => 'Rahim', 'last_name' => 'Uddin', 'join_date' => '2024-01-01',
        ]);
    }

    private function action(): ChangeOrgUnit
    {
        return app(ChangeOrgUnit::class);
    }

    public function test_a_new_employee_gets_a_first_row_from_the_join_date(): void
    {
        $row = $this->rahim->orgAssignments()->sole();

        $this->assertSame($this->finance->id, $row->org_unit_id);
        $this->assertSame('2024-01-01', $row->effective_from->toDateString());
        $this->assertTrue($row->isOpen());
    }

    public function test_a_change_closes_the_old_row_and_updates_the_employee(): void
    {
        $this->action()->handle($this->rahim, $this->sales->id, '2026-03-01', 'Transfer');

        [$new, $old] = $this->rahim->orgAssignments()->get()->all();   // newest first
        $this->assertSame($this->sales->id, $new->org_unit_id);
        $this->assertTrue($new->isOpen());
        $this->assertSame('Transfer', $new->reason);
        $this->assertSame('2026-02-28', $old->effective_to->toDateString());   // the day before
        $this->assertSame($this->sales->id, $this->rahim->refresh()->org_unit_id);
    }

    public function test_effective_on_finds_the_row_valid_on_a_day(): void
    {
        $this->action()->handle($this->rahim, $this->sales->id, '2026-03-01');
        $unitOn = fn (string $day) => $this->rahim->orgAssignments()->effectiveOn($day)->value('org_unit_id');

        $this->assertNull($unitOn('2023-12-31'));                    // before joining
        $this->assertSame($this->finance->id, $unitOn('2024-01-01'));
        $this->assertSame($this->finance->id, $unitOn('2026-02-28'));  // last day in Finance
        $this->assertSame($this->sales->id, $unitOn('2026-03-01'));
        $this->assertSame($this->sales->id, $this->rahim->orgAssignments()->current()->value('org_unit_id'));
    }

    public function test_dates_that_would_break_the_history_are_refused(): void
    {
        $this->assertSame('effective_from', $this->action()->problem($this->rahim, $this->sales->id, today()->addDay())['field'] ?? null);
        $this->assertNotNull($this->action()->problem($this->rahim, $this->sales->id, '2023-06-01'));   // before joining
        $this->assertNotNull($this->action()->problem($this->rahim, $this->sales->id, '2024-01-01'));   // same day as current start
        $this->assertSame('org_unit_id', $this->action()->problem($this->rahim, $this->finance->id, '2026-03-01')['field'] ?? null);
        $this->assertNull($this->action()->problem($this->rahim, $this->sales->id, '2026-03-01'));

        $this->expectException(LogicException::class);
        $this->action()->handle($this->rahim, $this->sales->id, today()->addDay());
    }

    public function test_undo_removes_the_newest_row_and_reopens_the_previous(): void
    {
        $this->assertNotNull($this->action()->undoProblem($this->rahim));   // only the first row

        $this->action()->handle($this->rahim, $this->sales->id, '2026-03-01');
        $this->action()->undoLast($this->rahim);

        $row = $this->rahim->orgAssignments()->sole();
        $this->assertSame($this->finance->id, $row->org_unit_id);
        $this->assertTrue($row->isOpen());
        $this->assertSame($this->finance->id, $this->rahim->refresh()->org_unit_id);
    }

    public function test_the_history_tab_shows_the_timeline(): void
    {
        $this->action()->handle($this->rahim, $this->sales->id, '2026-03-01', 'Restructuring');

        $this->get(route('hrm.employees.show', ['employee' => $this->rahim, 'tab' => 'history']))
            ->assertOk()
            ->assertSeeLivewire(OrgHistory::class)
            ->assertSeeInOrder(['Head Office › Sales', 'Restructuring', 'Head Office › Finance'])
            ->assertSee('Change org unit');
    }

    public function test_change_and_undo_from_the_tab(): void
    {
        Livewire::test(OrgHistory::class, ['employee' => $this->rahim])
            ->call('open')
            ->set('form.org_unit_id', (string) $this->sales->id)
            ->set('form.effective_from', '2026-03-01')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('hrm.employees.show', ['employee' => $this->rahim, 'tab' => 'history']));
        $this->assertSame($this->sales->id, $this->rahim->refresh()->org_unit_id);

        Livewire::test(OrgHistory::class, ['employee' => $this->rahim])->call('undo');
        $this->assertSame($this->finance->id, $this->rahim->refresh()->org_unit_id);
    }

    public function test_the_tab_shows_action_problems_under_the_right_field(): void
    {
        Livewire::test(OrgHistory::class, ['employee' => $this->rahim])
            ->call('open')
            ->set('form.org_unit_id', (string) $this->finance->id)
            ->set('form.effective_from', '2026-03-01')
            ->call('save')
            ->assertHasErrors('form.org_unit_id');

        Livewire::test(OrgHistory::class, ['employee' => $this->rahim])
            ->call('open')
            ->set('form.org_unit_id', (string) $this->sales->id)
            ->set('form.effective_from', today()->addWeek()->toDateString())
            ->call('save')
            ->assertHasErrors('form.effective_from');
    }

    public function test_a_finance_user_cannot_move_someone_out_of_finance_or_undo_back_into_sales(): void
    {
        $user = $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.employee.view', 'hrm.employee.update']);
        $user->role()->update(['org_unit_id' => $this->finance->id]);
        app(Permissions::class)->flush();

        Livewire::test(OrgHistory::class, ['employee' => $this->rahim])
            ->call('open')
            ->set('form.org_unit_id', (string) $this->sales->id)
            ->set('form.effective_from', '2026-03-01')
            ->call('save')
            ->assertHasErrors(['form.org_unit_id' => 'in']);

        // Karim came from Sales into Finance: undoing would put him back in Sales, outside this user's access.
        $karim = Employee::factory()->inUnit($this->sales)->create(['join_date' => '2024-01-01']);
        $this->action()->handle($karim, $this->finance->id, '2026-03-01');
        Livewire::test(OrgHistory::class, ['employee' => $karim])->call('undo')->assertDispatched('toast');
        $this->assertSame($this->finance->id, $karim->refresh()->org_unit_id);
    }

    public function test_viewers_see_the_history_but_cannot_change_it(): void
    {
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.employee.view']);

        $this->get(route('hrm.employees.show', ['employee' => $this->rahim, 'tab' => 'history']))
            ->assertOk()
            ->assertSee('Head Office › Finance')
            ->assertDontSee('Change org unit');
        Livewire::test(OrgHistory::class, ['employee' => $this->rahim])->call('open')->assertForbidden();
    }

    public function test_deleting_an_employee_deletes_their_history(): void
    {
        $this->rahim->delete();

        $this->assertSame(0, EmployeeOrgAssignment::count());
    }
}
