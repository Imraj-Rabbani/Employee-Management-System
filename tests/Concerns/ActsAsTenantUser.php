<?php

namespace Tests\Concerns;

use App\Core\Tenancy\TenantManager;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Models\Role;
use Illuminate\Support\Str;

/**
 * For tests of company pages: log in as a user of a REAL company database (migrated + seeded),
 * with that company active (like IdentifyTenant does for a real request).
 *
 *     $this->actingAsTenantUser();                                        Administrator (may do everything)
 *     $this->actingAsTenantUser(permissions: ['core.users.view']);        a role with only these permissions
 *     $this->actingAsTenantUser(permissions: []);                         a role with no permissions
 *     $this->actingAsTenantUser(tenant: $this->createMigratedTenant());   in a company you prepared
 *
 * The real database is needed because the sidebar and pages check permissions, which live in the tenant DB.
 */
trait ActsAsTenantUser
{
    use CreatesTenantDatabases;

    /**
     * @param  array<string, mixed>  $attributes  extra user attributes, e.g. ['name' => 'Rahim']
     * @param  list<string>|null  $permissions  null = Administrator
     */
    protected function actingAsTenantUser(array $attributes = [], ?Tenant $tenant = null, ?array $permissions = null): User
    {
        $tenant ??= $this->createMigratedTenant();
        $user = User::factory()->for($tenant)->create($attributes);

        app(TenantManager::class)->switchTo($tenant);

        if ($permissions === null) {
            Role::administrator()?->assignTo($user);
        } else {
            $role = Role::create(['name' => 'Test role '.Str::random(6)]);
            $role->syncPermissions($permissions);
            $role->assignTo($user);
        }

        $this->actingAs($user);

        return $user;
    }
}
