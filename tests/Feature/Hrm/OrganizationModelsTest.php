<?php

namespace Tests\Feature\Hrm;

use App\Core\Tenancy\TenantManager;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Models\Branch;
use App\Modules\Hrm\Models\CostCenter;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\EmploymentType;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Models\OrgUnitType;
use App\Modules\Hrm\Models\Position;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Models\Workflow;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\CreatesTenantDatabases;
use Tests\TestCase;

/**
 * Task 4.1: organisation tables, models and factories; org units and positions as trees. [ORG-01]
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
        foreach ([OrgUnitType::class, CostCenter::class, Branch::class, Designation::class, Grade::class,
            EmploymentType::class, OrgUnit::class, Position::class, Currency::class] as $model) {
            $row = $model::factory()->create();
            $this->assertTrue($row->exists, $model);
            $this->assertSame('tenant', $row->getConnectionName(), $model);
        }
    }

    public function test_a_new_company_gets_default_unit_levels_and_its_currency(): void
    {
        $types = OrgUnitType::orderBy('sequence')->get();

        $this->assertSame(['Company', 'Division', 'Department', 'Section'], $types->pluck('name')->all());
        $this->assertSame('Division', $types->firstWhere('name', 'Department')?->parentType?->name);
        $this->assertNull($types->first()?->parent_type_id);

        $this->assertSame('BDT', Currency::default()?->code);
        $this->assertSame('1.000000', Currency::default()?->exchange_rate);
    }

    // ---------------------------------------------------------------- the org unit tree

    public function test_parent_and_children_point_at_the_same_table(): void
    {
        [$head, $finance, $accounts] = $this->chain();

        $this->assertTrue($head->is($finance->parent));
        $this->assertSame(['Finance'], $head->children->pluck('name')->all());
        $this->assertSame(['Head Office'], OrgUnit::roots()->pluck('name')->all());
        $this->assertTrue($finance->is($accounts->parent));
    }

    public function test_ancestors_descendants_and_path(): void
    {
        [$head, $finance, $accounts] = $this->chain();
        $payroll = OrgUnit::factory()->childOf($accounts)->create(['name' => 'Payroll']);
        $hr = OrgUnit::factory()->childOf($head)->create(['name' => 'HR']);

        $this->assertSame([$accounts->id, $finance->id, $head->id], $payroll->ancestorIds());   // nearest first
        $this->assertEqualsCanonicalizing([$accounts->id, $payroll->id], $finance->descendantIds());
        $this->assertEqualsCanonicalizing([$finance->id, $accounts->id, $payroll->id, $hr->id], $head->descendantIds());
        $this->assertSame([], $payroll->descendantIds());
        $this->assertSame('Head Office › Finance › Accounts › Payroll', $payroll->path());
    }

    public function test_a_unit_cannot_be_moved_under_itself_or_its_own_sub_unit(): void
    {
        [$head, $finance, $accounts] = $this->chain();

        foreach ([$finance->id, $accounts->id] as $newParent) {
            try {
                $finance->update(['parent_id' => $newParent]);
                $this->fail('Expected a LogicException.');
            } catch (LogicException $e) {
                $this->assertStringContainsString('cannot be placed under itself', $e->getMessage());
            }
        }

        $this->assertSame($head->id, $finance->fresh()?->parent_id);
    }

    public function test_moving_a_unit_elsewhere_in_the_tree_works(): void
    {
        [$head, $finance, $accounts] = $this->chain();
        $hr = OrgUnit::factory()->childOf($head)->create(['name' => 'HR']);

        $accounts->update(['parent_id' => $hr->id]);

        $this->assertSame('Head Office › HR › Accounts', $accounts->fresh()?->path());
    }

    public function test_a_unit_with_sub_units_cannot_be_deleted(): void
    {
        [, $finance] = $this->chain();

        $this->expectException(QueryException::class);
        $finance->delete();
    }

    public function test_two_units_with_the_same_name_under_one_parent_are_refused(): void
    {
        [$head] = $this->chain();

        $this->expectException(QueryException::class);
        OrgUnit::factory()->childOf($head)->create(['name' => 'Finance']);
    }

    // ---------------------------------------------------------------- positions and grades

    public function test_positions_form_a_reporting_tree_with_grade_and_unit(): void
    {
        $manager = Position::factory()->create(['name' => 'Finance Manager']);
        $officer = Position::factory()->reportingTo($manager)->create(['name' => 'Accounts Officer']);

        $this->assertTrue($manager->is($officer->reportsTo));
        $this->assertSame(['Accounts Officer'], $manager->directReports->pluck('name')->all());
        $this->assertTrue($officer->orgUnit->is($manager->orgUnit));
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

    public function test_an_org_unit_used_as_a_workflow_scope_cannot_be_deleted(): void
    {
        $unit = OrgUnit::factory()->create();
        $type = ApprovalType::forKey('leave');   // registered by the leave migration (8.4)
        Workflow::create(['approval_type_id' => $type->id, 'name' => 'Unit only', 'scope_org_unit_id' => $unit->id]);

        $this->expectException(QueryException::class);
        $unit->delete();   // otherwise the workflow would silently apply to EVERY unit
    }

    public function test_workflow_and_role_scopes_must_point_at_real_rows(): void
    {
        $this->expectException(QueryException::class);
        Role::create(['name' => 'Branch HR', 'org_unit_id' => 999999]);
    }

    // ---------------------------------------------------------------- helpers

    /** @return array{0: OrgUnit, 1: OrgUnit, 2: OrgUnit} Head Office › Finance › Accounts */
    private function chain(): array
    {
        $head = OrgUnit::factory()->create(['name' => 'Head Office']);
        $finance = OrgUnit::factory()->childOf($head)->create(['name' => 'Finance']);
        $accounts = OrgUnit::factory()->childOf($finance)->create(['name' => 'Accounts']);

        return [$head, $finance, $accounts];
    }
}
