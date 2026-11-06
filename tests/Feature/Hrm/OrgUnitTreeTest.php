<?php

namespace Tests\Feature\Hrm;

use App\Modules\Hrm\Livewire\Organization\OrgUnitForm;
use App\Modules\Hrm\Livewire\Organization\OrgUnitTable;
use App\Modules\Hrm\Livewire\Organization\OrgUnitTree;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Models\OrgUnitType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 4.3: org unit tree view: nested display, drag to re-parent, "add sub-unit". [ORG-01]
 *
 * Structure used: Head Office (Company) › Operations, Sales (Divisions) › Accounts (Department, under Operations).
 */
class OrgUnitTreeTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private OrgUnit $head;

    private OrgUnit $operations;

    private OrgUnit $sales;

    private OrgUnit $accounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsTenantUser();   // Administrator; levels Company → Division → Department → Section are seeded

        $this->head = $this->unit('Head Office', 'Company');
        $this->operations = $this->unit('Operations', 'Division', $this->head);
        $this->sales = $this->unit('Sales', 'Division', $this->head);
        $this->accounts = $this->unit('Accounts', 'Department', $this->operations);
    }

    public function test_the_tree_is_the_default_view_and_the_list_is_one_click_away(): void
    {
        $this->get('/hrm/org/units')->assertOk()->assertSeeLivewire(OrgUnitTree::class)->assertDontSeeLivewire(OrgUnitTable::class);
        $this->get('/hrm/org/units?view=list')->assertOk()->assertSeeLivewire(OrgUnitTable::class);
    }

    public function test_units_are_shown_nested_under_their_parents(): void
    {
        Livewire::test(OrgUnitTree::class)
            ->assertViewHas('total', 4)
            ->assertViewHas('roots', fn (array $roots) => count($roots) === 1
                && $roots[0]['unit']->is($this->head)
                && collect($roots[0]['children'])->map(fn ($n) => $n['unit']->name)->all() === ['Operations', 'Sales']
                && $roots[0]['children'][0]['children'][0]['unit']->is($this->accounts))
            ->assertSeeInOrder(['Head Office', 'Operations', 'Accounts', 'Sales']);
    }

    public function test_dragging_a_unit_onto_another_moves_it(): void
    {
        Livewire::test(OrgUnitTree::class)
            ->call('move', $this->accounts->id, $this->sales->id)
            ->assertDispatched('toast', variant: 'success');

        $this->assertSame('Head Office › Sales › Accounts', $this->accounts->fresh()?->path());
    }

    public function test_a_unit_cannot_be_dropped_into_its_own_branch(): void
    {
        $this->level('Division')->update(['parent_type_id' => null]);   // only the loop rule is tested here

        Livewire::test(OrgUnitTree::class)
            ->call('move', $this->operations->id, $this->accounts->id)
            ->assertDispatched('toast', variant: 'danger');

        $this->assertSame($this->head->id, $this->operations->fresh()?->parent_id);
    }

    public function test_the_level_rule_applies_to_drag_and_drop_too(): void
    {
        Livewire::test(OrgUnitTree::class)
            ->call('move', $this->accounts->id, $this->head->id)   // a Department straight under a Company
            ->assertDispatched('toast', message: 'A Department must be placed under a Division.', variant: 'danger')
            ->call('move', $this->accounts->id, null)              // nor at the top
            ->assertDispatched('toast', variant: 'danger');

        $this->assertSame($this->operations->id, $this->accounts->fresh()?->parent_id);
    }

    public function test_a_unit_whose_level_has_no_parent_rule_can_go_to_the_top(): void
    {
        $this->level('Division')->update(['parent_type_id' => null]);

        Livewire::test(OrgUnitTree::class)->call('move', $this->sales->id, null)->assertDispatched('toast', variant: 'success');

        $this->assertNull($this->sales->fresh()?->parent_id);
    }

    public function test_two_units_with_the_same_name_cannot_end_up_under_one_parent(): void
    {
        $this->unit('Accounts', 'Department', $this->sales);

        Livewire::test(OrgUnitTree::class)
            ->call('move', $this->accounts->id, $this->sales->id)
            ->assertDispatched('toast', variant: 'danger');

        $this->assertSame($this->operations->id, $this->accounts->fresh()?->parent_id);
    }

    public function test_add_sub_unit_opens_the_form_with_the_parent_filled_in(): void
    {
        Livewire::test(OrgUnitForm::class)
            ->dispatch('create-org-unit', preset: ['parent_id' => $this->operations->id, 'not_a_field' => 'x'])
            ->assertSet('form.parent_id', $this->operations->id)
            ->assertSet('form.name', '')
            ->assertNotSet('form.not_a_field', 'x');
    }

    public function test_saving_in_the_form_refreshes_the_tree(): void
    {
        Livewire::test(OrgUnitTree::class)
            ->assertDontSee('Payroll')
            ->tap(fn () => $this->unit('Payroll', 'Department', $this->sales))
            ->dispatch('org-unit-saved')
            ->assertSee('Payroll');
    }

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
