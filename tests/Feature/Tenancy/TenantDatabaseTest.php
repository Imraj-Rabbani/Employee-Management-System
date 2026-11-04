<?php

namespace Tests\Feature\Tenancy;

use App\Core\Tenancy\TenantDatabase;
use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class TenantDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private TenantDatabase $databases;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databases = new TenantDatabase;
        $this->tenant = Tenant::factory()->create(['code' => 'test'.random_int(1000, 9999)]);
    }

    protected function tearDown(): void
    {
        // Always remove the real database this test created, even if the test failed.
        DB::purge('tenant');
        $this->databases->drop($this->tenant);

        // MySQL commits the open transaction on CREATE/DROP DATABASE, so RefreshDatabase
        // can no longer roll back the tenant row. Delete it ourselves.
        Tenant::query()->whereKey($this->tenant->id)->delete();

        parent::tearDown();
    }

    public function test_it_creates_and_drops_the_tenant_database(): void
    {
        $this->assertFalse($this->databases->exists($this->tenant));

        $this->databases->create($this->tenant);
        $this->assertTrue($this->databases->exists($this->tenant));

        $this->databases->drop($this->tenant);
        $this->assertFalse($this->databases->exists($this->tenant));
    }

    public function test_the_tenant_connection_can_use_the_new_database(): void
    {
        $this->databases->create($this->tenant);

        // This is what TenantManager will do in task 2.2.
        config(['database.connections.tenant' => $this->tenant->connectionConfig()]);
        DB::purge('tenant');

        Schema::connection('tenant')->create('notes', function (Blueprint $table) {
            $table->id();
            $table->string('text');
        });
        DB::connection('tenant')->table('notes')->insert(['text' => 'hello from the tenant DB']);

        $this->assertSame($this->tenant->database, DB::connection('tenant')->getDatabaseName());
        $this->assertSame('hello from the tenant DB', DB::connection('tenant')->table('notes')->value('text'));

        // The central database is untouched: it has no "notes" table.
        $this->assertFalse(Schema::connection('central')->hasTable('notes'));
    }

    public function test_connection_config_uses_the_tenant_overrides_only_when_set(): void
    {
        $config = $this->tenant->connectionConfig();
        $this->assertSame($this->tenant->database, $config['database']);
        $this->assertSame(config('database.connections.central.host'), $config['host']);

        $this->tenant->db_host = 'db2.example.com';
        $this->assertSame('db2.example.com', $this->tenant->connectionConfig()['host']);
    }

    public function test_unsafe_database_names_are_refused(): void
    {
        $evil = Tenant::factory()->make(['database' => 'x`; DROP DATABASE ems_clients; --']);

        $this->expectException(InvalidArgumentException::class);

        $this->databases->create($evil);
    }
}
