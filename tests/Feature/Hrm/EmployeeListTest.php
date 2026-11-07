<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Modules\Core\Services\Permissions;
use App\Modules\Hrm\Livewire\Employees\EmployeeTable;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmploymentType;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Hrm\Models\OrgUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 5.2: HRM → Employees list. [EMP-05]
 *
 * Structure used: Head Office › Finance › Accounts, and Head Office › Sales.
 * Employees: Rahim (Finance), Karim (Accounts, separated), Salma (Sales, Grade 5, Contract).
 */
class EmployeeListTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private OrgUnit $finance;

    private OrgUnit $sales;

    private Grade $grade5;

    private EmploymentType $contract;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = $this->actingAsTenantUser();
        $this->tenant = $admin->tenant;

        $head = OrgUnit::factory()->create(['name' => 'Head Office']);
        $this->finance = OrgUnit::factory()->childOf($head)->create(['name' => 'Finance']);
        $accounts = OrgUnit::factory()->childOf($this->finance)->create(['name' => 'Accounts']);
        $this->sales = OrgUnit::factory()->childOf($head)->create(['name' => 'Sales']);
        $this->grade5 = Grade::factory()->create(['name' => 'Grade 5']);
        $this->contract = EmploymentType::factory()->create(['name' => 'Contract']);

        Employee::factory()->inUnit($this->finance)->create(['code' => 'E-001', 'first_name' => 'Rahim', 'last_name' => 'Uddin']);
        Employee::factory()->inUnit($accounts)->separated()->create(['code' => 'E-002', 'first_name' => 'Karim', 'last_name' => 'Hasan']);
        Employee::factory()->inUnit($this->sales)->create([
            'code' => 'E-003', 'first_name' => 'Salma', 'last_name' => 'Begum', 'name_local' => 'সালমা',
            'grade_id' => $this->grade5->id, 'employment_type_id' => $this->contract->id,
        ]);
    }

    public function test_the_page_shows_the_list(): void
    {
        $this->get(route('hrm.employees.index'))
            ->assertOk()
            ->assertSeeLivewire(EmployeeTable::class)
            ->assertSee('Rahim Uddin')
            ->assertSee('Salma Begum');
    }

    public function test_the_page_needs_the_view_permission(): void
    {
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.employee.create']);

        $this->get(route('hrm.employees.index'))->assertForbidden();
    }

    public function test_a_user_limited_to_finance_only_sees_finance_and_below(): void
    {
        $user = $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.employee.view']);
        $user->role()->update(['org_unit_id' => $this->finance->id]);
        app(Permissions::class)->flush();

        Livewire::test(EmployeeTable::class)
            ->assertSee('Rahim Uddin')
            ->assertSee('Karim Hasan')
            ->assertDontSee('Salma Begum');
    }

    public function test_the_org_unit_filter_includes_sub_units(): void
    {
        Livewire::test(EmployeeTable::class)
            ->set('filters.org_unit', (string) $this->finance->id)
            ->assertSee('Rahim Uddin')
            ->assertSee('Karim Hasan')     // in Accounts, under Finance
            ->assertDontSee('Salma Begum');
    }

    public function test_status_grade_and_type_filters(): void
    {
        Livewire::test(EmployeeTable::class)
            ->set('filters.status', '3')   // Separated
            ->assertSee('Karim Hasan')
            ->assertDontSee('Rahim Uddin');

        Livewire::test(EmployeeTable::class)
            ->set('filters.grade_id', (string) $this->grade5->id)
            ->assertSee('Salma Begum')
            ->assertDontSee('Rahim Uddin');

        Livewire::test(EmployeeTable::class)
            ->set('filters.employment_type_id', (string) $this->contract->id)
            ->assertSee('Salma Begum')
            ->assertDontSee('Karim Hasan');
    }

    public function test_search_finds_code_last_name_and_local_name(): void
    {
        Livewire::test(EmployeeTable::class)->set('search', 'E-002')
            ->assertSee('Karim Hasan')->assertDontSee('Rahim Uddin');

        Livewire::test(EmployeeTable::class)->set('search', 'Uddin')
            ->assertSee('Rahim Uddin')->assertDontSee('Karim Hasan');

        Livewire::test(EmployeeTable::class)->set('search', 'সালমা')
            ->assertSee('Salma Begum')->assertDontSee('Rahim Uddin');
    }

    public function test_columns_can_be_hidden(): void
    {
        Livewire::test(EmployeeTable::class)
            ->assertSee('Head Office')     // org unit filter option path
            ->call('toggleColumn', 'mobile')
            ->assertSet('hiddenColumns', ['mobile'])
            ->call('toggleColumn', 'code')       // always visible: ignored
            ->assertSet('hiddenColumns', ['mobile']);
    }

    public function test_export_downloads_an_excel_file(): void
    {
        Livewire::test(EmployeeTable::class)
            ->call('export')
            ->assertFileDownloaded('employee-table-'.now()->format('Y-m-d').'.xlsx');
    }
}
