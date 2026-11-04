<?php

namespace App\Core\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Knows which tenant (company) the current request or job is working for,
 * and points the "tenant" database connection at that tenant's database.
 *
 * There is one instance per request/job (registered as "scoped" in AppServiceProvider).
 * Use the tenant() helper to read the current tenant.
 */
class TenantManager
{
    private ?Tenant $current = null;

    /** @var array<string, mixed> the "tenant" connection exactly as config/database.php defines it */
    private array $baseConfig;

    public function __construct()
    {
        /** @var array<string, mixed> $base */
        $base = config('database.connections.tenant');
        $this->baseConfig = $base;
    }

    /** Make $tenant the current tenant. All "tenant" connection queries go to its database from now on. */
    public function switchTo(Tenant $tenant): void
    {
        // Always build from the original settings, never from the previous tenant's.
        config(['database.connections.tenant' => $tenant->connectionConfig($this->baseConfig)]);

        // Laravel keeps a connection open once used. Close it so the next query reconnects with the new settings.
        DB::purge('tenant');

        $this->current = $tenant;
    }

    /** Back to "no tenant": the tenant connection has no database again. */
    public function forget(): void
    {
        config(['database.connections.tenant' => $this->baseConfig]);
        DB::purge('tenant');

        $this->current = null;
    }

    public function current(): ?Tenant
    {
        return $this->current;
    }

    public function check(): bool
    {
        return $this->current !== null;
    }
}
