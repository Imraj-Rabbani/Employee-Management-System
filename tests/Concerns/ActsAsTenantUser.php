<?php

namespace Tests\Concerns;

use App\Core\Tenancy\TenantManager;
use App\Models\Tenant;
use App\Models\User;

/**
 * For tests of module pages: log in as a user who belongs to a company,
 * with that company already active (like IdentifyTenant does for a real request).
 */
trait ActsAsTenantUser
{
    /** @param  array<string, mixed>  $attributes  extra user attributes, e.g. ['name' => 'Rahim'] */
    protected function actingAsTenantUser(array $attributes = [], ?Tenant $tenant = null): User
    {
        $tenant ??= Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create($attributes);

        $this->actingAs($user);
        app(TenantManager::class)->switchTo($tenant);

        return $user;
    }
}
