<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Modules\Core\Livewire\CurrencyForm;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Livewire\Organization\BranchForm;
use App\Modules\Hrm\Livewire\Organization\GradeForm;
use App\Modules\Hrm\Livewire\Organization\GradeTable;
use App\Modules\Hrm\Livewire\Organization\OrgUnitForm;
use App\Modules\Hrm\Livewire\Organization\OrgUnitTable;
use App\Modules\Hrm\Livewire\Organization\OrgUnitTypeForm;
use App\Modules\Hrm\Livewire\Organization\PositionForm;
use App\Modules\Hrm\Models\Branch;
use App\Modules\Hrm\Models\EmploymentType;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Models\OrgUnitType;
use App\Modules\Hrm\Models\Position;
use App\Modules\Workflow\Livewire\WorkflowForm;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 4.2: HRM → Organization screens (DataTable + modal form per table), Setup → Currencies,
 * and the workflow form's org unit / employment type scope. [ORG-01]
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
        $this->get('/dashboard')->assertSee(route('hrm.org.units'))->assertSee(route('core.currencies'));

        foreach (['units', 'positions', 'designations', 'grades', 'branches', 'cost-centers', 'employment-types', 'unit-levels'] as $section) {
            $this->get("/hrm/org/{$section}")->assertOk()->assertSee(route('hrm.org.grades'));
        }
        $this->get('/core/currencies')->assertOk()->assertSee('BDT');

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: []);
        $this->get('/hrm/org/units')->assertForbidden();
        $this->get('/core/currencies')->assertForbidden();
    }

    // ---------------------------------------------------------------- the shared LookupForm (via branches)

    public function test_a_record_is_created_edited_and_deleted_through_the_modal(): void
    {
        Livewire::test(BranchForm::class)
            ->dispatch('create-branch')
            ->assertSet('form.is_active', true)
            ->set('form.name', 'Chattogram')
            ->set('form.location', '')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('branch-saved');

        $branch = Branch::sole();
        $this->assertNull($branch->location);   // empty input → NULL

        Livewire::test(BranchForm::class)
            ->dispatch('edit-branch', id: $branch->id)
            ->assertSet('form.name', 'Chattogram')
            ->set('form.name', 'Chattogram Port')
            ->call('save')
            ->assertHasNoErrors()
            ->call('delete')
            ->assertDispatched('branch-saved');

        $this->assertSame(0, Branch::count());
    }

    public function test_names_must_be_unique(): void
    {
        Branch::factory()->create(['name' => 'Dhaka']);

        Livewire::test(BranchForm::class)
            ->dispatch('create-branch')
            ->set('form.name', 'Dhaka')
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

    // ---------------------------------------------------------------- org units

    public function test_a_unit_must_sit_under_the_level_its_type_requires(): void
    {
        // Seeded levels: Company → Division → Department → Section.
        $company = $this->unit('Head Office', 'Company');
        $division = $this->unit('Operations', 'Division', $company);

        $form = Livewire::test(OrgUnitForm::class)
            ->dispatch('create-org-unit')
            ->set('form.name', 'Accounts')
            ->set('form.org_unit_type_id', (string) $this->level('Department')->id)
            ->set('form.parent_id', (string) $company->id)   // a Department under a Company: wrong level
            ->call('save')
            ->assertHasErrors('form.parent_id');

        $form->set('form.parent_id', (string) $division->id)->call('save')->assertHasNoErrors();

        $this->assertSame('Head Office › Operations › Accounts', OrgUnit::where('name', 'Accounts')->sole()->path());
    }

    public function test_a_unit_cannot_be_moved_under_its_own_sub_unit_and_they_are_not_offered(): void
    {
        $company = $this->unit('Head Office', 'Company');
        $division = $this->unit('Operations', 'Division', $company);
        $this->level('Division')->update(['parent_type_id' => null]);   // so only the loop rule applies

        Livewire::test(OrgUnitForm::class)
            ->dispatch('edit-org-unit', id: $company->id)
            ->assertViewHas('parents', fn (array $parents) => ! isset($parents[$company->id]) && ! isset($parents[$division->id]))
            ->set('form.parent_id', (string) $division->id)
            ->call('save')
            ->assertHasErrors('form.parent_id');

        $this->assertNull($company->fresh()?->parent_id);
    }

    public function test_the_same_name_is_fine_under_different_parents(): void
    {
        $company = $this->unit('Head Office', 'Company');
        $ops = $this->unit('Operations', 'Division', $company);
        $sales = $this->unit('Sales', 'Division', $company);
        $this->unit('Accounts', 'Department', $ops);

        Livewire::test(OrgUnitForm::class)
            ->dispatch('create-org-unit')
            ->set('form.name', 'Accounts')
            ->set('form.org_unit_type_id', (string) $this->level('Department')->id)
            ->set('form.parent_id', (string) $ops->id)
            ->call('save')
            ->assertHasErrors(['form.name' => 'unique'])
            ->set('form.parent_id', (string) $sales->id)
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_the_list_shows_where_each_unit_sits(): void
    {
        $company = $this->unit('Head Office', 'Company');
        $this->unit('Operations', 'Division', $company);

        Livewire::test(OrgUnitTable::class)->assertSee('Operations')->assertSee('Head Office')->assertSee('Division');
    }

    public function test_a_unit_level_cannot_sit_under_itself(): void
    {
        $level = $this->level('Division');

        Livewire::test(OrgUnitTypeForm::class)
            ->dispatch('edit-org-unit-type', id: $level->id)
            ->set('form.parent_type_id', (string) $level->id)
            ->call('save')
            ->assertHasErrors('form.parent_type_id');
    }

    // ---------------------------------------------------------------- positions

    public function test_a_position_is_created_and_reporting_loops_are_refused(): void
    {
        $unit = OrgUnit::factory()->create();
        $manager = Position::factory()->create(['name' => 'Manager', 'org_unit_id' => $unit->id]);

        Livewire::test(PositionForm::class)
            ->dispatch('create-position')
            ->assertSet('form.is_vacant', true)
            ->set('form.name', 'Officer')
            ->set('form.org_unit_id', (string) $unit->id)
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

    // ---------------------------------------------------------------- currencies

    public function test_currency_codes_are_capitalised_and_the_default_keeps_rate_one(): void
    {
        Livewire::test(CurrencyForm::class)
            ->dispatch('create-currency')
            ->set('form.code', 'usd')
            ->set('form.name', 'US Dollar')
            ->set('form.exchange_rate', '121.5')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('121.500000', Currency::where('code', 'USD')->sole()->exchange_rate);

        Livewire::test(CurrencyForm::class)
            ->dispatch('edit-currency', id: Currency::default()?->id)
            ->set('form.exchange_rate', '2')
            ->call('save')
            ->assertHasErrors('form.exchange_rate');
    }

    // ---------------------------------------------------------------- workflow scope

    public function test_a_workflow_can_now_be_limited_to_an_org_unit_and_employment_type(): void
    {
        $unit = OrgUnit::factory()->create(['name' => 'Finance']);
        $type = EmploymentType::factory()->create(['name' => 'Contract']);
        $approvalType = ApprovalType::create(['key' => 'leave', 'name' => 'Leave']);
        $role = Role::create(['name' => 'Managers']);

        Livewire::test(WorkflowForm::class)
            ->dispatch('create-workflow')
            ->set('approvalTypeId', (string) $approvalType->id)
            ->set('name', 'Finance contract staff')
            ->set('scopeOrgUnitId', (string) $unit->id)
            ->set('scopeEmploymentTypeId', (string) $type->id)
            ->set('steps.0.approver_role_id', (string) $role->id)
            ->call('save')
            ->assertHasNoErrors();

        $workflow = Workflow::sole();
        $this->assertSame([$unit->id, $type->id], [$workflow->scope_org_unit_id, $workflow->scope_employment_type_id]);

        Livewire::test(WorkflowForm::class)
            ->dispatch('edit-workflow', id: $workflow->id)
            ->assertSet('scopeOrgUnitId', (string) $unit->id)
            ->set('scopeOrgUnitId', '')
            ->call('save');
        $this->assertNull($workflow->fresh()?->scope_org_unit_id);
    }

    // ---------------------------------------------------------------- helpers

    private function level(string $name): OrgUnitType
    {
        return OrgUnitType::where('name', $name)->firstOrFail();
    }

    private function unit(string $name, string $level, ?OrgUnit $parent = null): OrgUnit
    {
        return OrgUnit::factory()->create([
            'name' => $name,
            'org_unit_type_id' => $this->level($level)->id,
            'parent_id' => $parent?->id,
        ]);
    }
}
