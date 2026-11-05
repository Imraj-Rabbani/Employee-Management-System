<?php

namespace Tests\Feature\Core;

use App\Core\Permissions\PermissionRegistry;
use App\Core\Tenancy\TenantManager;
use App\Models\User;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\RolePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 2.9b + 2.9c: permission keys from the modules, permissions:sync, and the Gate rules. [FND-05]
 */
class PermissionsTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    // ---------------------------------------------------------------- 2.9b registry + sync

    public function test_every_module_registers_its_permissions(): void
    {
        $registry = app(PermissionRegistry::class);

        $this->assertTrue($registry->has('core.roles.manage'));
        $this->assertTrue($registry->has('hrm.employee.view'));
        $this->assertTrue($registry->has('payroll.run.approve'));
        $this->assertTrue($registry->has('workflow.setup.manage'));
        $this->assertSame(['core', 'hrm', 'payroll', 'workflow'], array_values(array_intersect(['core', 'hrm', 'payroll', 'workflow'], array_keys($registry->grouped()))));
    }

    public function test_keys_must_start_with_the_module_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PermissionRegistry)->addModule('hrm', ['X' => ['payroll.run.view' => 'Wrong module']]);
    }

    public function test_sync_removes_keys_that_no_longer_exist(): void
    {
        $this->actingAsTenantUser();
        $tenant = tenant();
        $role = Role::create(['name' => 'Old role']);
        $role->syncPermissions(['hrm.employee.view', 'hrm.removed.permission']);

        $this->artisan('permissions:sync', ['--dry-run' => true])
            ->expectsOutputToContain('would remove 1')
            ->assertSuccessful();

        app(TenantManager::class)->switchTo($tenant);   // the command ends with "no tenant"
        $this->assertSame(2, RolePermission::count(), 'Dry run changes nothing.');

        $this->artisan('permissions:sync')->expectsOutputToContain('removed 1')->assertSuccessful();

        app(TenantManager::class)->switchTo($tenant);
        $this->assertSame(['hrm.employee.view'], $role->permissionKeys());
    }

    // ---------------------------------------------------------------- 2.9c gate

    public function test_administrators_may_do_everything(): void
    {
        $user = $this->actingAsTenantUser();

        $this->assertTrue($user->can('core.roles.manage'));
        $this->assertTrue($user->can('payroll.run.approve'));
    }

    public function test_other_users_only_have_their_roles_permissions(): void
    {
        $user = $this->actingAsTenantUser(permissions: ['hrm.employee.view']);

        $this->assertTrue($user->can('hrm.employee.view'));
        $this->assertFalse($user->can('hrm.employee.delete'));
        $this->assertFalse($user->can('core.roles.manage'));
    }

    public function test_users_without_a_role_have_no_permissions(): void
    {
        $user = $this->actingAsTenantUser();
        $other = User::factory()->for($user->tenant)->create();

        $this->assertFalse($other->can('hrm.employee.view'));
    }

    public function test_switched_off_modules_deny_even_administrators(): void
    {
        $user = $this->actingAsTenantUser(tenant: $this->createMigratedTenant(['modules' => ['hrm']]));

        $this->assertTrue($user->can('hrm.employee.view'));
        $this->assertFalse($user->can('payroll.run.view'));
        $this->assertTrue($user->can('core.users.view'), 'Core is always on.');
    }

    public function test_a_company_administrator_is_not_a_super_admin(): void
    {
        $user = $this->actingAsTenantUser();

        $this->assertFalse($user->can('super-admin'));
        $this->get('/admin')->assertForbidden();
    }

    public function test_unknown_keys_are_denied(): void
    {
        $user = $this->actingAsTenantUser();

        $this->assertFalse($user->can('hrm.employe.view'));   // typo
    }

    public function test_changing_a_role_takes_effect_in_the_same_request(): void
    {
        $user = $this->actingAsTenantUser(permissions: []);
        $this->assertFalse(Gate::allows('hrm.employee.view'));

        $user->role()?->syncPermissions(['hrm.employee.view']);

        $this->assertTrue(Gate::allows('hrm.employee.view'));
    }

    // ---------------------------------------------------------------- pages and sidebar

    public function test_setup_pages_need_their_permission(): void
    {
        $this->actingAsTenantUser(permissions: ['core.users.view']);

        $this->get('/core/users')->assertOk();
        $this->get('/core/roles')->assertForbidden();
        $this->get('/core/settings')->assertForbidden();
        $this->get('/core/fiscal-years')->assertForbidden();
    }

    public function test_the_sidebar_only_shows_allowed_items(): void
    {
        $this->actingAsTenantUser(permissions: ['core.users.view']);

        $this->get('/dashboard')
            ->assertSee(route('core.users'))
            ->assertDontSee(route('core.roles'))
            ->assertDontSee(route('core.settings'));
    }
}
