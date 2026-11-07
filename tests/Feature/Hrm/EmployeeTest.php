<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Services\Permissions;
use App\Modules\Hrm\Enums\EmployeeStatus;
use App\Modules\Hrm\Enums\Gender;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 5.1: employees table, model, factory, policy and the VisibleToCurrentUser global scope. [EMP-01, FND-05]
 *
 * Structure used: Head Office › Finance › Accounts, and Head Office › Sales.
 */
class EmployeeTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private OrgUnit $head;

    private OrgUnit $finance;

    private OrgUnit $accounts;

    private OrgUnit $sales;

    /** The company of the first login, reused by later logins in the same test. */
    private ?Tenant $tenant = null;

    private function buildTree(): void
    {
        $this->head = OrgUnit::factory()->create(['name' => 'Head Office']);
        $this->finance = OrgUnit::factory()->childOf($this->head)->create(['name' => 'Finance']);
        $this->accounts = OrgUnit::factory()->childOf($this->finance)->create(['name' => 'Accounts']);
        $this->sales = OrgUnit::factory()->childOf($this->head)->create(['name' => 'Sales']);
    }

    /** Logs in a user whose role has $permissions and is limited to $unit (null = no limit). */
    private function actingAsScopedUser(?OrgUnit $unit, array $permissions = ['hrm.employee.view']): User
    {
        $user = $this->actingAsTenantUser(tenant: $this->tenant, permissions: $permissions);
        $user->role()->update(['org_unit_id' => $unit?->id]);
        app(Permissions::class)->flush();

        return $user;
    }

    public function test_the_model_casts_enums_and_dates_and_builds_the_name(): void
    {
        $this->actingAsTenantUser();

        $employee = Employee::factory()->create([
            'first_name' => 'Rahim', 'last_name' => 'Uddin', 'gender' => Gender::Male, 'join_date' => '2024-01-15',
        ])->fresh();

        $this->assertSame('Rahim Uddin', $employee->name);
        $this->assertSame(Gender::Male, $employee->gender);
        $this->assertSame(EmployeeStatus::Active, $employee->status);
        $this->assertSame('2024-01-15', $employee->join_date->toDateString());
        $this->assertFalse($employee->has_shifting);
    }

    public function test_relations_to_org_data_and_supervisor(): void
    {
        $this->actingAsTenantUser();

        $boss = Employee::factory()->create();
        $employee = Employee::factory()->withJob()->create(['supervisor_id' => $boss->id]);

        $this->assertNotNull($employee->grade);
        $this->assertNotNull($employee->designation);
        $this->assertNotNull($employee->employmentType);
        $this->assertTrue($employee->orgUnit->is(OrgUnit::find($employee->org_unit_id)));
        $this->assertTrue($employee->supervisor->is($boss));
        $this->assertTrue($boss->subordinates->first()->is($employee));
        $this->assertCount(1, $employee->orgUnit->employees);
    }

    public function test_a_grade_in_use_cannot_be_deleted(): void
    {
        $this->actingAsTenantUser();
        $grade = Grade::factory()->create();
        Employee::factory()->create(['grade_id' => $grade->id]);

        $this->expectException(QueryException::class);
        $grade->delete();
    }

    public function test_deleting_a_supervisor_clears_the_field(): void
    {
        $this->actingAsTenantUser();
        $boss = Employee::factory()->create();
        $employee = Employee::factory()->create(['supervisor_id' => $boss->id]);

        $boss->delete();

        $this->assertNull($employee->fresh()->supervisor_id);
    }

    public function test_an_administrator_sees_every_employee(): void
    {
        $this->actingAsTenantUser();
        $this->buildTree();
        Employee::factory()->inUnit($this->finance)->create();
        Employee::factory()->inUnit($this->sales)->create();

        $this->assertSame(2, Employee::count());
    }

    public function test_a_role_limited_to_an_org_unit_sees_that_unit_and_the_units_below_it(): void
    {
        $this->buildTreeAsAdminThen(fn () => $this->actingAsScopedUser($this->finance));

        $names = Employee::orderBy('first_name')->pluck('first_name')->all();

        $this->assertSame(['InAccounts', 'InFinance'], $names);
        $this->assertNull(Employee::where('first_name', 'InSales')->first());
    }

    public function test_a_role_without_an_org_unit_sees_everyone(): void
    {
        $this->buildTreeAsAdminThen(fn () => $this->actingAsScopedUser(null));

        $this->assertSame(3, Employee::count());
    }

    public function test_a_limited_user_always_sees_their_own_record(): void
    {
        $this->buildTreeAsAdminThen(function () {
            $user = $this->actingAsScopedUser($this->finance);
            Employee::withoutGlobalScope(VisibleToCurrentUser::class)
                ->where('first_name', 'InSales')->update(['user_id' => $user->id]);
        });

        $this->assertNotNull(Employee::where('first_name', 'InSales')->first());
    }

    public function test_without_a_login_there_is_no_limit(): void
    {
        $this->buildTreeAsAdminThen(fn () => $this->actingAsScopedUser($this->finance));

        Auth::forgetUser();   // like an artisan command or a queued job

        $this->assertSame(3, Employee::count());
    }

    public function test_policy_needs_the_permission_and_the_org_scope(): void
    {
        $this->buildTreeAsAdminThen(fn () => $this->actingAsScopedUser(
            $this->finance, ['hrm.employee.view', 'hrm.employee.update'],
        ));

        $inFinance = Employee::where('first_name', 'InFinance')->first();
        $inSales = Employee::withoutGlobalScope(VisibleToCurrentUser::class)->where('first_name', 'InSales')->first();

        $this->assertTrue(Gate::allows('viewAny', Employee::class));
        $this->assertTrue(Gate::allows('view', $inFinance));
        $this->assertTrue(Gate::allows('update', $inFinance));
        $this->assertFalse(Gate::allows('view', $inSales));
        $this->assertFalse(Gate::allows('update', $inSales));
        $this->assertFalse(Gate::allows('delete', $inFinance));   // no hrm.employee.delete
        $this->assertFalse(Gate::allows('create', Employee::class));
    }

    public function test_own_record_is_viewable_without_permission_but_not_editable(): void
    {
        $this->buildTreeAsAdminThen(function () {
            $user = $this->actingAsScopedUser($this->finance, []);
            Employee::withoutGlobalScope(VisibleToCurrentUser::class)
                ->where('first_name', 'InSales')->update(['user_id' => $user->id]);
        });

        $mine = Employee::where('first_name', 'InSales')->first();

        $this->assertFalse(Gate::allows('viewAny', Employee::class));
        $this->assertTrue(Gate::allows('view', $mine));
        $this->assertFalse(Gate::allows('update', $mine));
    }

    /**
     * As an Administrator: build the tree and one employee in Finance, Accounts and Sales.
     * Then log in as the user made by $login (same company).
     */
    private function buildTreeAsAdminThen(callable $login): void
    {
        $admin = $this->actingAsTenantUser();
        $this->buildTree();
        Employee::factory()->inUnit($this->finance)->create(['first_name' => 'InFinance']);
        Employee::factory()->inUnit($this->accounts)->create(['first_name' => 'InAccounts']);
        Employee::factory()->inUnit($this->sales)->create(['first_name' => 'InSales']);

        // Without a tenant, actingAsTenantUser would create a new company; log in to this one.
        $this->tenant = $admin->tenant;
        $login();
    }
}
