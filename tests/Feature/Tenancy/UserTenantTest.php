<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_belongs_to_a_tenant_and_tenant_has_users(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'SSS Ltd']);
        $user = User::factory()->for($tenant)->create();

        $this->assertSame('SSS Ltd', $user->tenant?->name);
        $this->assertTrue($tenant->users->contains($user));
    }

    public function test_user_without_tenant_is_allowed(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->tenant_id);
        $this->assertNull($user->tenant);
    }

    public function test_tenant_id_cannot_be_mass_assigned(): void
    {
        $tenant = Tenant::factory()->create();

        $user = new User(['name' => 'X', 'email' => 'x@example.com', 'password' => 'secret', 'tenant_id' => $tenant->id]);

        $this->assertNull($user->tenant_id);
    }

    public function test_tenant_with_users_cannot_be_deleted(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->for($tenant)->create();

        $this->expectException(QueryException::class);

        $tenant->delete();
    }

    public function test_user_uses_the_central_connection(): void
    {
        $this->assertSame('central', (new User)->getConnectionName());
    }
}
