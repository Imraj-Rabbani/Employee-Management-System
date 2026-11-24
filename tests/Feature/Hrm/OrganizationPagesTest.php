<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Modules\Hrm\Livewire\Organization\EntityForm;
use App\Modules\Hrm\Livewire\Organization\EntityTable;
use App\Modules\Hrm\Livewire\Organization\EntityTypeForm;
use App\Modules\Hrm\Livewire\Organization\GradeForm;
use App\Modules\Hrm\Livewire\Organization\GradeTable;
use App\Modules\Hrm\Livewire\Organization\PositionForm;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Hrm\Models\EntityType;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Hrm\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * HRM → Organization screens (DataTable + modal form per table), Setup → Currencies,
 * and the workflow form's entity / employment type scope.
 */
class OrganizationPagesTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createMigratedTenant();
        $this->actingAsTenantUser(tenant: $this->tenant);   // Administrator
    }

    // ---------------------------------------------------------------- pages

    public function test_every_section_opens_from_the_menu_and_needs_the_permission(): void
    {
        $this->get('/dashboard')->assertSee(route('hrm.organization'));

        foreach (['entity-types', 'entities', 'positions', 'designations', 'grades', 'employment-types'] as $section) {
            $this->get("/hrm/org/{$section}")->assertOk()->assertSee(route('hrm.org.grades'));
        }

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: []);
        $this->get('/hrm/org/entities')->assertForbidden();
    }

    // ---------------------------------------------------------------- the shared LookupForm (via entity types)

    public function test_a_record_is_created_edited_and_deleted_through_the_modal(): void
    {
        Livewire::test(EntityTypeForm::class)
            ->dispatch('create-entity-type')
            ->assertSet('form.is_active', true)
            ->set('form.name', 'Zone')
            ->set('form.sequence', '9')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('entity-type-saved');

        $zone = EntityType::where('name', 'Zone')->sole();

        Livewire::test(EntityTypeForm::class)
            ->dispatch('edit-entity-type', id: $zone->id)
            ->assertSet('form.name', 'Zone')
            ->set('form.name', 'Region')
            ->call('save')
            ->assertHasNoErrors()
            ->call('delete')
            ->assertDispatched('entity-type-saved');

        $this->assertFalse(EntityType::whereIn('name', ['Zone', 'Region'])->exists());
    }

    public function test_names_must_be_unique(): void
    {
        Livewire::test(EntityTypeForm::class)
            ->dispatch('create-entity-type')
            ->set('form.name', 'Department')   // seeded
            ->call('save')
            ->assertHasErrors(['form.name' => 'unique']);
    }

    public function test_a_record_still_in_use_cannot_be_deleted(): void
    {
        $grade = Grade::factory()->create();
        Position::factory()->create(['grade_id' => $grade->id]);

        Livewire::test(GradeForm::class)
            ->dispatch('edit-grade', id: $grade->id)
            ->call('delete')
            ->assertHasErrors('form.code');   // the first field shows "still in use"

        $this->assertTrue($grade->fresh()?->exists);
    }

    public function test_grades_are_listed_in_their_order(): void
    {
        Grade::factory()->create(['name' => 'Grade Two', 'sequence' => 2]);
        Grade::factory()->create(['name' => 'Grade One', 'sequence' => 1]);

        Livewire::test(GradeTable::class)->assertSeeInOrder(['Grade One', 'Grade Two']);
    }

    // ---------------------------------------------------------------- entities

    public function test_an_entity_is_created_with_its_type_head_and_optional_parent(): void
    {
        $dhaka = $this->entity('Dhaka', 'Division');
        $head = Employee::factory()->create(['first_name' => 'Karim']);

        Livewire::test(EntityForm::class)
            ->dispatch('create-entity')
            ->set('form.entity_type_id', (string) $this->type('Branch')->id)
            ->assertViewHas('parents', fn (array $parents) => isset($parents[$dhaka->id]))
            ->set('form.name', 'Gulshan')
            ->set('form.code', 'BR-GUL')
            ->set('form.head_employee_id', (string) $head->id)
            ->set('form.parent_id', (string) $dhaka->id)
            ->call('save')
            ->assertHasNoErrors();

        $gulshan = Entity::where('name', 'Gulshan')->sole();
        $this->assertSame('Branch', $gulshan->type->name);
        $this->assertTrue($gulshan->head->is($head));
        $this->assertTrue($gulshan->parent->is($dhaka));
    }

    public function test_the_parent_must_be_another_type_and_same_type_entities_are_not_offered(): void
    {
        $gulshan = $this->entity('Gulshan', 'Branch');
        $dhaka = $this->entity('Dhaka', 'Division');

        Livewire::test(EntityForm::class)
            ->dispatch('create-entity')
            ->set('form.entity_type_id', (string) $this->type('Branch')->id)
            ->assertViewHas('parents', fn (array $parents) => ! isset($parents[$gulshan->id]) && isset($parents[$dhaka->id]))
            ->set('form.name', 'Motijheel')
            ->set('form.parent_id', (string) $gulshan->id)   // sent anyway: refused
            ->call('save')
            ->assertHasErrors('form.parent_id');

        // No loops: Dhaka cannot go under Gulshan while Gulshan is under Dhaka.
        $gulshan->update(['parent_id' => $dhaka->id]);
        Livewire::test(EntityForm::class)
            ->dispatch('edit-entity', id: $dhaka->id)
            ->set('form.parent_id', (string) $gulshan->id)
            ->call('save')
            ->assertHasErrors('form.parent_id');
    }

    public function test_the_same_name_is_refused_in_one_type_but_fine_in_another(): void
    {
        $this->entity('Dhaka', 'Division');

        Livewire::test(EntityForm::class)
            ->dispatch('create-entity')
            ->set('form.name', 'Dhaka')
            ->set('form.entity_type_id', (string) $this->type('Division')->id)
            ->call('save')
            ->assertHasErrors(['form.name' => 'unique'])
            ->set('form.entity_type_id', (string) $this->type('Branch')->id)
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_the_list_shows_type_head_parent_and_employee_count_and_filters_by_type(): void
    {
        $dhaka = $this->entity('Dhaka', 'Division');
        $head = Employee::factory()->create(['first_name' => 'Karim', 'last_name' => 'Ahmed']);
        $gulshan = Entity::factory()->ofType($this->type('Branch'))->create(['name' => 'Gulshan', 'parent_id' => $dhaka->id, 'head_employee_id' => $head->id]);
        Employee::factory()->count(2)->inEntity($gulshan)->create();

        Livewire::test(EntityTable::class)
            ->assertSee('Gulshan')->assertSee('Karim Ahmed')->assertSee('Dhaka (Division)')
            ->assertViewHas('rows', fn ($rows) => $rows->firstWhere('name', 'Gulshan')->current_assignments_count === 2)
            ->set('entityTypeId', (string) $this->type('Branch')->id)
            ->assertViewHas('rows', fn ($rows) => $rows->pluck('name')->all() === ['Gulshan']);

        // The Entity types tab links to the filtered list.
        $this->get(route('hrm.org.entity-types'))->assertSee(route('hrm.org.entities', ['type' => $this->type('Branch')->id]), false);
    }

    public function test_an_entity_type_with_entities_cannot_be_deleted(): void
    {
        $this->entity('Gulshan', 'Branch');

        Livewire::test(EntityTypeForm::class)
            ->dispatch('edit-entity-type', id: $this->type('Branch')->id)
            ->call('delete')
            ->assertHasErrors('form.name');

        $this->assertTrue($this->type('Branch')->exists);
    }

    // ---------------------------------------------------------------- positions

    public function test_a_position_is_created_and_reporting_loops_are_refused(): void
    {
        $unit = Entity::factory()->create();
        $manager = Position::factory()->create(['name' => 'Manager', 'entity_id' => $unit->id]);

        Livewire::test(PositionForm::class)
            ->dispatch('create-position')
            ->assertSet('form.is_vacant', true)
            ->set('form.name', 'Officer')
            ->set('form.entity_id', (string) $unit->id)
            ->set('form.parent_id', (string) $manager->id)
            ->set('form.effective_from', '2026-01-01')
            ->call('save')
            ->assertHasNoErrors();

        $officer = Position::where('name', 'Officer')->sole();
        $this->assertTrue($manager->is($officer->reportsTo));
        $this->assertSame('2026-01-01', $officer->effective_from?->toDateString());

        // Manager → reports to Officer would be a loop.
        Livewire::test(PositionForm::class)
            ->dispatch('edit-position', id: $manager->id)
            ->assertSet('form.effective_from', '')
            ->set('form.parent_id', (string) $officer->id)
            ->call('save')
            ->assertHasErrors('form.parent_id');
    }

    // ---------------------------------------------------------------- helpers

    private function type(string $name): EntityType
    {
        return EntityType::where('name', $name)->firstOrFail();
    }

    private function entity(string $name, string $type): Entity
    {
        return Entity::factory()->ofType($this->type($type))->create(['name' => $name]);
    }
}
