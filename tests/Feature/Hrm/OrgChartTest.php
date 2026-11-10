<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Modules\Core\Services\Permissions;
use App\Modules\Hrm\Livewire\OrgChart;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 5.9: org chart from supervisors and positions. [ORG-02]
 *
 *   Ceo (Head Office)
 *   ├── Fatima (Finance)
 *   │   └── Rahim (Finance)
 *   │       └── Nadia (Finance)
 *   └── Selim (Sales)
 *   Old (separated, reported to Ceo)
 */
class OrgChartTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private OrgUnit $finance;

    /** @var array<string, Employee> */
    private array $e = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->actingAsTenantUser()->tenant;
        $head = OrgUnit::factory()->create(['name' => 'Head Office']);
        $this->finance = OrgUnit::factory()->childOf($head)->create(['name' => 'Finance']);
        $sales = OrgUnit::factory()->childOf($head)->create(['name' => 'Sales']);

        $make = fn (string $name, OrgUnit $unit, ?string $boss = null, array $extra = []) => $this->e[$name] = Employee::factory()->inUnit($unit)->create([
            'first_name' => $name, 'last_name' => 'X', 'supervisor_id' => $boss ? $this->e[$boss]->id : null, ...$extra,
        ]);
        $make('Ceo', $head);
        $make('Fatima', $this->finance, 'Ceo');
        $make('Rahim', $this->finance, 'Fatima');
        $make('Nadia', $this->finance, 'Rahim');
        $make('Selim', $sales, 'Ceo');
        $make('Old', $head, 'Ceo')->update(['status' => 3, 'separation_date' => '2025-01-01']);
    }

    public function test_the_page_starts_with_the_top_and_their_direct_reports(): void
    {
        $this->get(route('hrm.org.chart'))
            ->assertOk()
            ->assertSeeLivewire(OrgChart::class)
            ->assertSeeInOrder(['Ceo X', 'Fatima X', 'Selim X'])
            ->assertDontSee('Rahim X')       // Fatima's branch is still closed
            ->assertDontSee('Old X');        // separated
    }

    public function test_branches_open_and_close(): void
    {
        Livewire::test(OrgChart::class)
            ->call('toggle', $this->e['Fatima']->id)
            ->assertSee('Rahim X')
            ->assertDontSee('Nadia X')
            ->call('toggle', $this->e['Fatima']->id)
            ->assertDontSee('Rahim X');
    }

    public function test_focus_opens_the_chain_down_to_the_person(): void
    {
        Livewire::withQueryParams(['focus' => $this->e['Nadia']->id])
            ->test(OrgChart::class)
            ->assertSeeInOrder(['Ceo X', 'Fatima X', 'Rahim X', 'Nadia X']);

        $this->get(route('hrm.employees.show', $this->e['Nadia']))
            ->assertSee(route('hrm.org.chart', ['focus' => $this->e['Nadia']->id]), false);
    }

    public function test_search_jumps_to_a_person(): void
    {
        Livewire::test(OrgChart::class)
            ->set('search', 'nad')
            ->assertSee('Nadia X')
            ->call('show', $this->e['Nadia']->id)
            ->assertSet('focus', $this->e['Nadia']->id)
            ->assertSet('search', '')
            ->assertSee('Rahim X');
    }

    public function test_someone_whose_supervisor_you_cannot_see_is_shown_at_the_top(): void
    {
        $user = $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.employee.view']);
        $user->role()->update(['org_unit_id' => $this->finance->id]);
        app(Permissions::class)->flush();

        Livewire::test(OrgChart::class)
            ->assertSee('Fatima X')          // her supervisor (Ceo) is outside Finance → she is a top card
            ->assertDontSee('Ceo X')
            ->assertDontSee('Selim X')
            ->assertSee('Rahim X');          // top cards are open
    }

    public function test_the_positions_view_shows_holders_and_vacancies(): void
    {
        $cfo = Position::factory()->create(['name' => 'CFO', 'org_unit_id' => $this->finance->id, 'employee_id' => $this->e['Fatima']->id, 'is_vacant' => false]);
        Position::factory()->create(['name' => 'Accountant', 'org_unit_id' => $this->finance->id, 'parent_id' => $cfo->id]);

        Livewire::withQueryParams(['view' => 'positions'])
            ->test(OrgChart::class)
            ->assertSeeInOrder(['CFO', 'Fatima X', 'Accountant', 'Vacant']);
    }

    public function test_a_holder_you_may_not_see_is_shown_as_filled(): void
    {
        $sales = OrgUnit::where('name', 'Sales')->sole();
        Position::factory()->create(['name' => 'Analyst', 'org_unit_id' => $this->finance->id, 'employee_id' => $this->e['Selim']->id, 'is_vacant' => false]);
        Position::factory()->create(['name' => 'Sales Lead', 'org_unit_id' => $sales->id]);

        $user = $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.employee.view']);
        $user->role()->update(['org_unit_id' => $this->finance->id]);
        app(Permissions::class)->flush();

        Livewire::withQueryParams(['view' => 'positions'])
            ->test(OrgChart::class)
            ->assertSee('Analyst')
            ->assertSee('Filled')
            ->assertDontSee('Selim X')
            ->assertDontSee('Sales Lead');   // position in a unit outside your access
    }

    public function test_the_chart_needs_the_view_permission(): void
    {
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.employee.create']);

        $this->get(route('hrm.org.chart'))->assertForbidden();
    }
}
