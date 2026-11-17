<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Modules\Core\Services\Permissions;
use App\Modules\Hrm\Actions\ChangeEntity;
use App\Modules\Hrm\Livewire\Employees\EntityHistory;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeEntity;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Hrm\Models\EntityType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Effective-dated entity history, one per entity type (HasEffectiveDates, ChangeEntity, History tab).
 *
 * Rahim joined on 2024-01-01 in Department Finance and Branch Gulshan.
 * Other entities: Department Sales, Branch Motijheel.
 */
class EntityAssignmentTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private Entity $finance;

    private Entity $sales;

    private Entity $gulshan;

    private Entity $motijheel;

    private Employee $rahim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->actingAsTenantUser()->tenant;

        $department = EntityType::firstWhere('name', 'Department');
        $branch = EntityType::firstWhere('name', 'Branch');
        $this->finance = Entity::factory()->ofType($department)->create(['name' => 'Finance']);
        $this->sales = Entity::factory()->ofType($department)->create(['name' => 'Sales']);
        $this->gulshan = Entity::factory()->ofType($branch)->create(['name' => 'Gulshan']);
        $this->motijheel = Entity::factory()->ofType($branch)->create(['name' => 'Motijheel']);

        $this->rahim = Employee::factory()->create(['first_name' => 'Rahim', 'last_name' => 'Uddin', 'join_date' => '2024-01-01']);
        $this->action()->assignOnJoining($this->rahim, [$this->finance->id, $this->gulshan->id, '']);
    }

    private function action(): ChangeEntity
    {
        return app(ChangeEntity::class);
    }

    public function test_joining_gives_one_open_row_per_type_from_the_join_date(): void
    {
        $rows = $this->rahim->currentEntityAssignments()->get();

        $this->assertEqualsCanonicalizing([$this->finance->id, $this->gulshan->id], $rows->pluck('entity_id')->all());
        $this->assertSame(['2024-01-01'], $rows->map(fn ($r) => $r->effective_from->toDateString())->unique()->values()->all());
        $this->assertEqualsCanonicalizing([$this->finance->id, $this->gulshan->id], $this->rahim->entityIds());
        $this->assertTrue($this->rahim->entityOf($this->finance->entity_type_id)?->is($this->finance));
        $this->assertSame('Finance (Department), Gulshan (Branch)', $this->rahim->entitiesLabel());
    }

    public function test_two_entities_of_one_type_are_refused_on_joining(): void
    {
        $this->expectException(LogicException::class);
        $this->action()->assignOnJoining(Employee::factory()->create(), [$this->finance->id, $this->sales->id]);
    }

    public function test_a_change_closes_only_the_row_of_the_same_type(): void
    {
        $this->action()->handle($this->rahim, $this->sales->id, '2026-03-01', 'Transfer');

        [$new, $old] = $this->rahim->entityAssignments()->where('entity_type_id', $this->sales->entity_type_id)->get()->all();   // newest first
        $this->assertSame($this->sales->id, $new->entity_id);
        $this->assertTrue($new->isOpen());
        $this->assertSame('Transfer', $new->reason);
        $this->assertSame('2026-02-28', $old->effective_to->toDateString());   // the day before

        // The Branch did not move.
        $this->assertEqualsCanonicalizing([$this->sales->id, $this->gulshan->id], $this->rahim->entityIds());
    }

    public function test_effective_on_finds_the_row_valid_on_a_day(): void
    {
        $this->action()->handle($this->rahim, $this->sales->id, '2026-03-01');
        $departmentOn = fn (string $day) => $this->rahim->entityAssignments()
            ->where('entity_type_id', $this->finance->entity_type_id)->effectiveOn($day)->value('entity_id');

        $this->assertNull($departmentOn('2023-12-31'));                    // before joining
        $this->assertSame($this->finance->id, $departmentOn('2024-01-01'));
        $this->assertSame($this->finance->id, $departmentOn('2026-02-28'));  // last day in Finance
        $this->assertSame($this->sales->id, $departmentOn('2026-03-01'));
    }

    public function test_dates_that_would_break_the_history_are_refused(): void
    {
        $this->assertSame('effective_from', $this->action()->problem($this->rahim, $this->sales->id, today()->addDay())['field'] ?? null);
        $this->assertNotNull($this->action()->problem($this->rahim, $this->sales->id, '2023-06-01'));   // before joining
        $this->assertNotNull($this->action()->problem($this->rahim, $this->sales->id, '2024-01-01'));   // same day as current start
        $this->assertSame('entity_id', $this->action()->problem($this->rahim, $this->finance->id, '2026-03-01')['field'] ?? null);
        $this->assertNull($this->action()->problem($this->rahim, $this->sales->id, '2026-03-01'));

        $this->expectException(LogicException::class);
        $this->action()->handle($this->rahim, $this->sales->id, today()->addDay());
    }

    public function test_a_first_entity_of_a_new_type_can_be_added_later(): void
    {
        $dhaka = Entity::factory()->ofType(EntityType::firstWhere('name', 'Division'))->create(['name' => 'Dhaka']);

        $this->action()->handle($this->rahim, $dhaka->id, '2026-01-01');

        $this->assertContains($dhaka->id, $this->rahim->entityIds());
        $this->assertNotNull($this->action()->undoProblem($this->rahim, $dhaka->entity_type_id));   // its first row stays
    }

    public function test_undo_removes_the_newest_row_of_that_type_and_reopens_the_previous(): void
    {
        $typeId = $this->finance->entity_type_id;
        $this->assertNotNull($this->action()->undoProblem($this->rahim, $typeId));   // only the first row

        $this->action()->handle($this->rahim, $this->sales->id, '2026-03-01');
        $this->action()->handle($this->rahim, $this->motijheel->id, '2026-04-01');
        $this->action()->undoLast($this->rahim, $typeId);

        $this->assertEqualsCanonicalizing([$this->finance->id, $this->motijheel->id], $this->rahim->entityIds());
    }

    public function test_the_history_tab_shows_the_timeline(): void
    {
        $this->action()->handle($this->rahim, $this->sales->id, '2026-03-01', 'Restructuring');

        $this->get(route('hrm.employees.show', ['employee' => $this->rahim, 'tab' => 'history']))
            ->assertOk()
            ->assertSeeLivewire(EntityHistory::class)
            ->assertSeeInOrder(['Sales', 'Restructuring', 'Finance'])
            ->assertSee('Gulshan')
            ->assertSee('Change entity');
    }

    public function test_change_and_undo_from_the_tab(): void
    {
        Livewire::test(EntityHistory::class, ['employee' => $this->rahim])
            ->call('open')
            ->set('form.entity_id', (string) $this->sales->id)
            ->set('form.effective_from', '2026-03-01')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('hrm.employees.show', ['employee' => $this->rahim, 'tab' => 'history']));
        $this->assertContains($this->sales->id, $this->rahim->entityIds());

        Livewire::test(EntityHistory::class, ['employee' => $this->rahim])
            ->assertViewHas('undoable', fn (array $undoable) => array_values($undoable) === [$this->sales->entity_type_id])
            ->call('undo', $this->sales->entity_type_id);
        $this->assertContains($this->finance->id, $this->rahim->entityIds());
    }

    public function test_the_tab_shows_action_problems_under_the_right_field(): void
    {
        Livewire::test(EntityHistory::class, ['employee' => $this->rahim])
            ->call('open')
            ->set('form.entity_id', (string) $this->finance->id)
            ->set('form.effective_from', '2026-03-01')
            ->call('save')
            ->assertHasErrors('form.entity_id');

        Livewire::test(EntityHistory::class, ['employee' => $this->rahim])
            ->call('open')
            ->set('form.entity_id', (string) $this->sales->id)
            ->set('form.effective_from', today()->addWeek()->toDateString())
            ->call('save')
            ->assertHasErrors('form.effective_from');
    }

    public function test_a_finance_user_may_change_the_branch_but_not_move_someone_out_of_finance(): void
    {
        $user = $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.employee.view', 'hrm.employee.update']);
        $user->role()->update(['entity_id' => $this->finance->id]);
        app(Permissions::class)->flush();

        Livewire::test(EntityHistory::class, ['employee' => $this->rahim])
            ->call('open')
            ->set('form.entity_id', (string) $this->sales->id)
            ->set('form.effective_from', '2026-03-01')
            ->call('save')
            ->assertHasErrors(['form.entity_id' => 'in']);

        Livewire::test(EntityHistory::class, ['employee' => $this->rahim])
            ->call('open')
            ->set('form.entity_id', (string) $this->motijheel->id)   // another type: allowed
            ->set('form.effective_from', '2026-03-01')
            ->call('save')
            ->assertHasNoErrors();

        // Karim came from Sales into Finance: undoing would put him back in Sales, outside this user's access.
        $karim = Employee::factory()->inEntity($this->sales)->create(['join_date' => '2024-01-01']);
        $this->action()->handle($karim, $this->finance->id, '2026-03-01');
        Livewire::test(EntityHistory::class, ['employee' => $karim])->call('undo', $this->finance->entity_type_id)->assertDispatched('toast');
        $this->assertSame([$this->finance->id], $karim->entityIds());
    }

    public function test_viewers_see_the_history_but_cannot_change_it(): void
    {
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.employee.view']);

        $this->get(route('hrm.employees.show', ['employee' => $this->rahim, 'tab' => 'history']))
            ->assertOk()
            ->assertSee('Finance')
            ->assertDontSee('Change entity');
        Livewire::test(EntityHistory::class, ['employee' => $this->rahim])->call('open')->assertForbidden();
    }

    public function test_deleting_an_employee_deletes_their_history(): void
    {
        $this->rahim->delete();

        $this->assertSame(0, EmployeeEntity::count());
    }
}
