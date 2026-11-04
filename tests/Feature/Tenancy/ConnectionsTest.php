<?php

namespace Tests\Feature\Tenancy;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConnectionsTest extends TestCase
{
    public function test_central_is_the_default_connection(): void
    {
        $this->assertSame('central', config('database.default'));
        $this->assertSame('central', DB::connection()->getName());
    }

    public function test_central_connection_reaches_its_database(): void
    {
        // In tests this is hrm_testing (set in phpunit.xml); in the app it is ems_clients.
        $this->assertSame(env('DB_DATABASE'), DB::connection('central')->getDatabaseName());
        $this->assertSame(1, DB::connection('central')->selectOne('select 1 as ok')->ok);
    }

    public function test_tenant_connection_has_no_database_until_a_tenant_is_chosen(): void
    {
        $this->assertNull(config('database.connections.tenant.database'));
        $this->assertSame(config('database.connections.central.host'), config('database.connections.tenant.host'));
    }
}
