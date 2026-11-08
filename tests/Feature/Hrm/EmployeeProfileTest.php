<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Modules\Core\Services\Permissions;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\OrgUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 5.3: the employee profile page with tabs. [EMP-01]
 *
 * Structure: Head Office › Finance and Head Office › Sales.
 * Rahim (Finance) reports to Salma (Sales).
 */
class EmployeeProfileTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private OrgUnit $finance;

    private Employee $rahim;

    private Employee $salma;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->actingAsTenantUser()->tenant;

        $head = OrgUnit::factory()->create(['name' => 'Head Office']);
        $this->finance = OrgUnit::factory()->childOf($head)->create(['name' => 'Finance']);
        $sales = OrgUnit::factory()->childOf($head)->create(['name' => 'Sales']);

        $this->salma = Employee::factory()->inUnit($sales)->create(['code' => 'E-002', 'first_name' => 'Salma', 'last_name' => 'Begum']);
        $this->rahim = Employee::factory()->inUnit($this->finance)->create([
            'code' => 'E-001', 'first_name' => 'Rahim', 'last_name' => 'Uddin', 'name_local' => 'রহিম',
            'designation_id' => Designation::factory()->create(['name' => 'Senior Officer'])->id,
            'national_id' => '1990123456789',
            'supervisor_id' => $this->salma->id,
        ]);
    }

    public function test_the_profile_shows_the_header_and_personal_tab(): void
    {
        $this->get(route('hrm.employees.show', $this->rahim))
            ->assertOk()
            ->assertSee('Rahim Uddin')
            ->assertSee('রহিম')
            ->assertSee('Senior Officer')
            ->assertSee('Head Office › Finance')
            ->assertSee('1990123456789');
    }

    public function test_the_job_tab_links_to_the_supervisor(): void
    {
        $this->get(route('hrm.employees.show', ['employee' => $this->rahim, 'tab' => 'job']))
            ->assertOk()
            ->assertSee('Salma Begum (E-002)')
            ->assertSee(route('hrm.employees.show', $this->salma), false);
    }

    public function test_every_tab_renders_and_unknown_tabs_fall_back(): void
    {
        foreach (['personal', 'job', 'family', 'education', 'work-history', 'documents', 'bank', 'history'] as $tab) {
            $this->get(route('hrm.employees.show', ['employee' => $this->rahim, 'tab' => $tab]))->assertOk();
        }

        $this->get(route('hrm.employees.show', ['employee' => $this->rahim, 'tab' => 'nonsense']))
            ->assertOk()
            ->assertSee('Personal details');
    }

    public function test_the_list_links_to_the_profile(): void
    {
        $this->get(route('hrm.employees.index'))
            ->assertSee(route('hrm.employees.show', $this->rahim), false);
    }

    public function test_someone_outside_your_org_units_gives_404(): void
    {
        $user = $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.employee.view']);
        $user->role()->update(['org_unit_id' => $this->finance->id]);
        app(Permissions::class)->flush();

        $this->get(route('hrm.employees.show', ['employee' => $this->rahim, 'tab' => 'job']))
            ->assertOk()
            ->assertSee('Salma Begum')                                              // supervisor from Sales: name shown ...
            ->assertDontSee(route('hrm.employees.show', $this->salma), false);   // ... but no link
        $this->get(route('hrm.employees.show', $this->salma))->assertNotFound();
    }

    public function test_the_page_needs_the_view_permission(): void
    {
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.employee.create']);

        $this->get(route('hrm.employees.show', $this->rahim))->assertForbidden();
    }
}
