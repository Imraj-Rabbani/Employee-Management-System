<?php

namespace Tests\Feature\Tenancy;

use App\Core\Tenancy\IdentifyTenant;
use App\Core\Tenancy\TenantManager;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IdentifyTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_page_switches_to_the_users_company(): void
    {
        $tenant = Tenant::factory()->create(['code' => 'sss']);
        $this->actingAs(User::factory()->for($tenant)->create());

        $this->get('/hrm/ping')->assertOk();

        $this->assertTrue($tenant->is(tenant()));
        $this->assertSame('ems_tenant_sss', config('database.connections.tenant.database'));
    }

    public function test_user_without_a_company_is_refused(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/hrm/ping')->assertForbidden();
        $this->assertFalse(app(TenantManager::class)->check());
    }

    public function test_inactive_company_is_refused(): void
    {
        $this->actingAs(User::factory()->for(Tenant::factory()->inactive())->create());

        $this->get('/hrm/ping')->assertForbidden();
    }

    public function test_expired_company_is_refused(): void
    {
        $this->actingAs(User::factory()->for(Tenant::factory()->state(['expires_at' => now()->subDay()]))->create());

        $this->get('/hrm/ping')->assertForbidden();
    }

    public function test_company_expiring_in_the_future_is_allowed(): void
    {
        $this->actingAs(User::factory()->for(Tenant::factory()->state(['expires_at' => now()->addMonth()]))->create());

        $this->get('/hrm/ping')->assertOk();
    }

    public function test_guests_still_go_to_login_first(): void
    {
        $this->get('/hrm/ping')->assertRedirect('/login');
    }

    public function test_livewire_actions_also_run_the_middleware(): void
    {
        $this->assertContains(IdentifyTenant::class, Livewire::getPersistentMiddleware());
    }
}
