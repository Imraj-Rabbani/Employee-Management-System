<?php

namespace Tests\Feature\Tenancy;

use App\Core\Tenancy\TenantManager;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantManagerTest extends TestCase
{
    use RefreshDatabase;

    private TenantManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = app(TenantManager::class);
    }

    public function test_no_tenant_is_active_at_first(): void
    {
        $this->assertFalse($this->manager->check());
        $this->assertNull($this->manager->current());
        $this->assertNull(tenant());
    }

    public function test_switching_points_the_tenant_connection_at_the_tenant_database(): void
    {
        $tenant = Tenant::factory()->create(['code' => 'sss']);

        $this->manager->switchTo($tenant);

        $this->assertTrue($this->manager->check());
        $this->assertSame($tenant, tenant());
        $this->assertSame('ems_tenant_sss', config('database.connections.tenant.database'));
    }

    public function test_switching_never_carries_settings_over_from_the_previous_tenant(): void
    {
        $onOtherServer = Tenant::factory()->create(['db_host' => 'db2.example.com', 'db_username' => 'other']);
        $normal = Tenant::factory()->create();

        $this->manager->switchTo($onOtherServer);
        $this->assertSame('db2.example.com', config('database.connections.tenant.host'));

        $this->manager->switchTo($normal);
        $this->assertSame(config('database.connections.central.host'), config('database.connections.tenant.host'));
        $this->assertSame(config('database.connections.central.username'), config('database.connections.tenant.username'));
        $this->assertSame($normal->database, config('database.connections.tenant.database'));
    }

    public function test_forget_clears_the_tenant(): void
    {
        $this->manager->switchTo(Tenant::factory()->create());

        $this->manager->forget();

        $this->assertNull(tenant());
        $this->assertNull(config('database.connections.tenant.database'));
    }

    public function test_the_same_manager_is_shared_within_a_request(): void
    {
        $this->assertSame(app(TenantManager::class), app(TenantManager::class));
    }
}
