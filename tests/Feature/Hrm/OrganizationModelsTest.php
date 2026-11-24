<?php

namespace Tests\Feature\Hrm;

use App\Core\Tenancy\TenantManager;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmploymentType;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Hrm\Models\EntityType;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Hrm\Models\Position;
use App\Modules\Workflow\Enums\ApproverType;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowStep;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\CreatesTenantDatabases;
use Tests\TestCase;

/**
 * Organisation tables, models and factories: entity types, flat entities (optional parent), positions as a tree.
 */
class OrganizationModelsTest extends TestCase
{
    use CreatesTenantDatabases;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantManager::class)->switchTo($this->createMigratedTenant());
    }

    public function test_every_model_has_a_working_factory(): void
    {
        foreach ([EntityType::class, Designation::class, Grade::class,
            EmploymentType::class, Entity::class, Position::class] as $model) {
            $row = $model::factory()->create();
            $this->assertTrue($row->exists, $model);
            $this->assertSame('tenant', $row->getConnectionName(), $model);
        }
    }

    public function test_a_new_company_gets_default_entity_types(): void
    {
        $this->assertSame(['Department', 'Division', 'Branch', 'Location', 'Area'], EntityType::ordered()->pluck('name')->all());
    }

    // ---------------------------------------------------------------- entities

    public function test_entities_belong_to_a_type_and_may_have_a_head(): void
    {
        $department = EntityType::firstWhere('name', 'Department');
        $head = Employee::factory()->create();
        $finance = Entity::factory()->ofType($department)->create(['name' => 'Finance', 'head_employee_id' => $head->id]);

        $this->assertTrue($finance->type->is($department));
        $this->assertTrue($finance->head->is($head));
        $this->assertSame('Finance (Department)', $finance->label());
        $this->assertSame(['Finance'], $department->entities->pluck('name')->all());
    }

    public function test_the_same_name_is_refused_within_one_type_but_fine_in_another(): void
    {
        [$department, $branch] = [EntityType::firstWhere('name', 'Department'), EntityType::firstWhere('name', 'Branch')];
        Entity::factory()->ofType($department)->create(['name' => 'Dhaka']);
        Entity::factory()->ofType($branch)->create(['name' => 'Dhaka']);   // fine: another type

        $this->expectException(QueryException::class);
        Entity::factory()->ofType($department)->create(['name' => 'Dhaka']);
    }

    public function test_the_optional_parent_must_be_another_type_and_never_loop(): void
    {
        [$division, $branch] = [EntityType::firstWhere('name', 'Division'), EntityType::firstWhere('name', 'Branch')];
        $dhaka = Entity::factory()->ofType($division)->create(['name' => 'Dhaka']);
        $gulshan = Entity::factory()->ofType($branch)->create(['name' => 'Gulshan', 'parent_id' => $dhaka->id]);
        $motijheel = Entity::factory()->ofType($branch)->create(['name' => 'Motijheel']);

        $this->assertTrue($gulshan->parent->is($dhaka));
        $this->assertSame(['Gulshan'], $dhaka->children->pluck('name')->all());
        $this->assertSame([$dhaka->id], $gulshan->ancestorIds());

        $this->assertSame('The parent must be an entity of another type.', $motijheel->parentProblem($gulshan));
        $this->assertSame('An entity cannot be its own parent.', $gulshan->parentProblem($gulshan));
        $this->assertSame('Gulshan is already under this entity.', $dhaka->parentProblem($gulshan));
        $this->assertNull($motijheel->parentProblem($dhaka));

        // The model refuses it too, not only the form.
        $this->expectException(LogicException::class);
        $dhaka->update(['parent_id' => $gulshan->id]);
    }

    public function test_an_entity_with_children_or_employees_cannot_be_deleted(): void
    {
        $dhaka = Entity::factory()->create();
        Entity::factory()->create(['parent_id' => $dhaka->id]);

        try {
            $dhaka->delete();
            $this->fail('Expected a QueryException.');
        } catch (QueryException) {
            $this->assertTrue($dhaka->exists);
        }

        $finance = Entity::factory()->create();
        Employee::factory()->inEntity($finance)->create();

        $this->expectException(QueryException::class);
        $finance->delete();
    }

    public function test_an_entity_type_in_use_cannot_be_deleted(): void
    {
        $type = EntityType::factory()->create();
        Entity::factory()->ofType($type)->create();

        $this->expectException(QueryException::class);
        $type->delete();
    }

    // ---------------------------------------------------------------- positions and grades

    public function test_positions_form_a_reporting_tree_with_grade_and_entity(): void
    {
        $manager = Position::factory()->create(['name' => 'Finance Manager']);
        $officer = Position::factory()->reportingTo($manager)->create(['name' => 'Accounts Officer']);

        $this->assertTrue($manager->is($officer->reportsTo));
        $this->assertSame(['Accounts Officer'], $manager->directReports->pluck('name')->all());
        $this->assertTrue($officer->entity->is($manager->entity));
        $this->assertNotNull($officer->grade);
        $this->assertTrue($officer->is_vacant);
    }

    public function test_grades_are_listed_by_sequence(): void
    {
        Grade::factory()->create(['name' => 'G3', 'sequence' => 3]);
        Grade::factory()->create(['name' => 'G1', 'sequence' => 1]);
        Grade::factory()->create(['name' => 'G2', 'sequence' => 2]);

        $this->assertSame(['G1', 'G2', 'G3'], Grade::ordered()->pluck('name')->all());
    }

    // ---------------------------------------------------------------- foreign keys added to earlier tables

    public function test_an_entity_chosen_on_a_workflow_level_cannot_be_deleted(): void
    {
        $unit = Entity::factory()->create();
        $workflow = Workflow::create(['approval_type_id' => ApprovalType::forKey('leave')?->id]);   // type registered by the leave migration
        WorkflowStep::create([
            'workflow_id' => $workflow->id, 'level' => 1, 'approver_type' => ApproverType::SpecificEntityHead,
            'approver_entity_type_id' => $unit->entity_type_id, 'approver_entity_id' => $unit->id,
        ]);

        $this->expectException(QueryException::class);
        $unit->delete();   // otherwise level 1 would point at nothing
    }

    public function test_workflow_and_role_scopes_must_point_at_real_rows(): void
    {
        $this->expectException(QueryException::class);
        Role::create(['name' => 'Branch HR', 'entity_id' => 999999]);
    }
}
