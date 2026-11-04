<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_is_stored_in_the_central_database(): void
    {
        $tenant = Tenant::factory()->create(['code' => 'sss', 'name' => 'SSS Ltd']);

        $this->assertSame('central', $tenant->getConnectionName());
        $this->assertDatabaseHas('tenants', ['code' => 'sss', 'database' => 'ems_tenant_sss'], 'central');
    }

    public function test_database_name_follows_the_pattern(): void
    {
        $this->assertSame('ems_tenant_sss', Tenant::databaseNameFor('SSS'));
    }

    public function test_db_password_is_encrypted_at_rest_and_hidden_from_json(): void
    {
        $tenant = Tenant::factory()->create(['db_password' => 'secret123']);

        $raw = DB::connection('central')->table('tenants')->where('id', $tenant->id)->value('db_password');

        $this->assertNotSame('secret123', $raw);
        $this->assertSame('secret123', $tenant->fresh()?->db_password);
        $this->assertArrayNotHasKey('db_password', $tenant->toArray());
    }

    public function test_modules_are_cast_to_an_array(): void
    {
        $tenant = Tenant::factory()->create(['modules' => ['hrm', 'payroll']]);

        $this->assertSame(['hrm', 'payroll'], $tenant->fresh()?->modules);
    }

    public function test_code_must_be_unique(): void
    {
        Tenant::factory()->create(['code' => 'sss', 'database' => 'ems_tenant_sss']);

        $this->expectException(UniqueConstraintViolationException::class);

        Tenant::factory()->create(['code' => 'sss', 'database' => 'ems_tenant_other']);
    }
}
