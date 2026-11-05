<?php

namespace Tests\Feature\Core;

use App\Core\Tenancy\TenantManager;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Database\Seeders\RolesSeeder;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\RolePermission;
use App\Modules\Core\Models\UserRole;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantDatabases;
use Tests\TestCase;

/**
 * Task 2.9a: roles, their permissions, and one role per login. [FND-05]
 */
class RoleTest extends TestCase
{
    use CreatesTenantDatabases;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createMigratedTenant();
        app(TenantManager::class)->switchTo($this->tenant);
    }

    public function test_the_seeder_creates_one_administrator_role(): void
    {
        $this->seed(RolesSeeder::class);
        $this->seed(RolesSeeder::class);   // safe to run twice

        $this->assertSame(1, Role::where('is_admin', true)->count());
        $this->assertSame('Administrator', Role::administrator()?->name);
    }

    public function test_is_admin_cannot_be_set_from_a_form(): void
    {
        $role = Role::create(['name' => 'Sneaky', 'is_admin' => true]);

        $this->assertFalse($role->fresh()?->is_admin);
    }

    public function test_a_role_keeps_a_list_of_permission_keys(): void
    {
        $role = Role::create(['name' => 'HR Officer']);

        $role->syncPermissions(['hrm.employee.view', 'hrm.employee.create', 'hrm.employee.view']);
        $this->assertSame(['hrm.employee.create', 'hrm.employee.view'], $role->permissionKeys());

        $role->syncPermissions(['hrm.employee.view', 'payroll.run.view']);
        $this->assertSame(['hrm.employee.view', 'payroll.run.view'], $role->permissionKeys());
    }

    public function test_a_user_has_one_role_and_assigning_replaces_it(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        $officer = Role::create(['name' => 'HR Officer']);
        $manager = Role::create(['name' => 'HR Manager']);

        $this->assertNull($user->role());

        $officer->assignTo($user);
        $this->assertSame('HR Officer', $user->role()?->name);

        $manager->assignTo($user);
        $this->assertSame('HR Manager', $user->role()?->name);
        $this->assertSame(1, UserRole::where('user_id', $user->id)->count());
    }

    public function test_role_is_null_outside_the_users_own_company(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        Role::create(['name' => 'HR Officer'])->assignTo($user);

        app(TenantManager::class)->switchTo(Tenant::factory()->create());
        $this->assertNull($user->role());

        app(TenantManager::class)->forget();
        $this->assertNull($user->role());
    }

    public function test_a_role_in_use_cannot_be_deleted(): void
    {
        $role = Role::create(['name' => 'HR Officer']);
        $role->assignTo(User::factory()->for($this->tenant)->create());

        $this->expectException(QueryException::class);
        $role->delete();
    }

    public function test_deleting_an_unused_role_deletes_its_permissions(): void
    {
        $role = Role::create(['name' => 'Temp']);
        $role->syncPermissions(['hrm.employee.view']);

        $role->delete();

        $this->assertSame(0, RolePermission::count());
    }
}
