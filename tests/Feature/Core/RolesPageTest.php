<?php

namespace Tests\Feature\Core;

use App\Models\User;
use App\Modules\Core\Livewire\RoleForm;
use App\Modules\Core\Livewire\RolesTable;
use App\Modules\Core\Livewire\UserRoleForm;
use App\Modules\Core\Livewire\UsersTable;
use App\Modules\Core\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 2.9d + 2.9e: Setup → Roles & Permissions, and giving users a role. [FND-05]
 */
class RolesPageTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    // ---------------------------------------------------------------- 2.9d roles screen

    public function test_the_roles_page_lists_the_administrator_role(): void
    {
        $this->actingAsTenantUser();

        $this->get('/core/roles')
            ->assertOk()
            ->assertSee('New role')
            ->assertSeeLivewire(RolesTable::class)
            ->assertSee('Administrator')
            ->assertSee('Full access');
    }

    public function test_a_role_is_created_with_permissions(): void
    {
        $this->actingAsTenantUser();

        Livewire::test(RoleForm::class)
            ->dispatch('create-role')
            ->assertSee('View employees')   // permission labels from hrm/permissions.php
            ->set('name', 'HR Officer')
            ->set('description', 'Day-to-day HR work')
            ->set('permissions', ['hrm.employee.view', 'hrm.employee.create'])
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('role-saved');

        $role = Role::where('name', 'HR Officer')->firstOrFail();
        $this->assertSame(['hrm.employee.create', 'hrm.employee.view'], $role->permissionKeys());
    }

    public function test_only_known_permissions_are_accepted(): void
    {
        $this->actingAsTenantUser();

        Livewire::test(RoleForm::class)
            ->dispatch('create-role')
            ->set('name', 'Hacker')
            ->set('permissions', ['made.up.permission'])
            ->call('save')
            ->assertHasErrors(['permissions.0']);
    }

    public function test_the_form_only_offers_permissions_of_switched_on_modules(): void
    {
        $this->actingAsTenantUser(tenant: $this->createMigratedTenant(['modules' => ['hrm']]));

        Livewire::test(RoleForm::class)
            ->dispatch('create-role')
            ->assertSee('View employees')
            ->assertDontSee('Approve payroll runs');
    }

    public function test_editing_the_administrator_role_never_limits_it(): void
    {
        $this->actingAsTenantUser();
        $admin = Role::administrator();

        Livewire::test(RoleForm::class)
            ->dispatch('edit-role', id: $admin?->id)
            ->assertSet('isAdmin', true)
            ->assertSee('Full access')
            ->set('name', 'Super User')
            ->set('permissions', ['hrm.employee.view'])
            ->call('save');

        $admin = $admin?->fresh();
        $this->assertSame('Super User', $admin?->name);
        $this->assertTrue($admin?->is_admin);
        $this->assertSame([], $admin?->permissionKeys());
    }

    public function test_roles_can_be_deleted_only_when_unused_and_not_administrator(): void
    {
        $me = $this->actingAsTenantUser();
        $used = Role::create(['name' => 'Used']);
        $used->assignTo(User::factory()->for($me->tenant)->create());
        $unused = Role::create(['name' => 'Unused']);

        Livewire::test(RoleForm::class)->dispatch('edit-role', id: Role::administrator()?->id)->call('delete')->assertHasErrors('name');
        Livewire::test(RoleForm::class)->dispatch('edit-role', id: $used->id)->call('delete')->assertHasErrors('name');
        Livewire::test(RoleForm::class)->dispatch('edit-role', id: $unused->id)->call('delete')->assertHasNoErrors();

        $this->assertNull($unused->fresh());
        $this->assertNotNull($used->fresh());
    }

    public function test_the_roles_page_needs_its_permission(): void
    {
        $this->actingAsTenantUser(permissions: ['core.users.view']);

        $this->get('/core/roles')->assertForbidden();
    }

    // ---------------------------------------------------------------- 2.9e users' roles

    public function test_the_users_list_shows_each_users_role(): void
    {
        $me = $this->actingAsTenantUser(['name' => 'Admin Person']);
        Role::create(['name' => 'HR Officer'])->assignTo(User::factory()->for($me->tenant)->create(['name' => 'Rahim']));
        User::factory()->for($me->tenant)->create(['name' => 'No Role Person']);

        Livewire::test(UsersTable::class)
            ->assertSee('Administrator')
            ->assertSee('HR Officer')
            ->assertSee('No role')
            ->assertSeeHtml("\$dispatch('edit-user-role'");
    }

    public function test_users_who_cannot_manage_see_roles_but_no_links_or_bulk_actions(): void
    {
        $this->actingAsTenantUser(permissions: ['core.users.view']);

        Livewire::test(UsersTable::class)
            ->assertDontSeeHtml("\$dispatch('edit-user-role'")
            ->assertDontSee('Mark verified');

        $this->get('/core/users')->assertOk()->assertDontSeeLivewire(UserRoleForm::class);
    }

    public function test_an_administrator_changes_a_users_role(): void
    {
        $me = $this->actingAsTenantUser();
        $rahim = User::factory()->for($me->tenant)->create(['name' => 'Rahim']);
        $officer = Role::create(['name' => 'HR Officer']);

        Livewire::test(UserRoleForm::class)
            ->dispatch('edit-user-role', id: $rahim->id)
            ->assertSet('userName', 'Rahim')
            ->set('roleId', $officer->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('user-role-saved');

        $this->assertSame('HR Officer', $rahim->role()?->name);
    }

    public function test_nobody_can_change_their_own_role(): void
    {
        $me = $this->actingAsTenantUser();
        $officer = Role::create(['name' => 'HR Officer']);

        Livewire::test(UserRoleForm::class)
            ->dispatch('edit-user-role', id: $me->id)
            ->set('roleId', $officer->id)
            ->call('save')
            ->assertHasErrors('roleId');

        $this->assertTrue($me->role()?->is_admin);
    }

    public function test_only_administrators_can_give_the_administrator_role(): void
    {
        $me = $this->actingAsTenantUser(permissions: ['core.users.view', 'core.users.manage']);
        $friend = User::factory()->for($me->tenant)->create();

        Livewire::test(UserRoleForm::class)
            ->dispatch('edit-user-role', id: $friend->id)
            ->set('roleId', Role::administrator()?->id)
            ->call('save')
            ->assertHasErrors('roleId');

        $this->assertNull($friend->role());
    }

    public function test_only_administrators_can_change_an_administrators_role(): void
    {
        $me = $this->actingAsTenantUser(permissions: ['core.users.view', 'core.users.manage']);
        $boss = User::factory()->for($me->tenant)->create();
        Role::administrator()?->assignTo($boss);

        Livewire::test(UserRoleForm::class)
            ->dispatch('edit-user-role', id: $boss->id)
            ->set('roleId', Role::create(['name' => 'Nobody'])->id)
            ->call('save')
            ->assertHasErrors('roleId');

        $this->assertTrue($boss->role()?->is_admin);
    }

    public function test_users_of_another_company_cannot_be_changed(): void
    {
        // Make the other company first: creating it ends with "no tenant active".
        $stranger = User::factory()->for($this->createMigratedTenant())->create();
        $this->actingAsTenantUser();

        Livewire::test(UserRoleForm::class)
            ->dispatch('edit-user-role', id: $stranger->id)
            ->assertNotFound();

        $this->assertNull($stranger->fresh()?->role());
    }

    public function test_the_role_form_needs_the_manage_permission(): void
    {
        $this->actingAsTenantUser(permissions: ['core.users.view']);

        Livewire::test(UserRoleForm::class)->assertForbidden();
    }
}
