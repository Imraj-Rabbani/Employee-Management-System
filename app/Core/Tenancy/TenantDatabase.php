<?php

namespace App\Core\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates and drops the MySQL database of a tenant (ems_tenant_{code}).
 *
 * Runs on the central connection, so the tenant DB is created on the same MySQL
 * server as ems_clients. Tenants with their own db_host need their DB created there by hand.
 */
class TenantDatabase
{
    public function exists(Tenant $tenant): bool
    {
        return DB::connection('central')->selectOne(
            'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$this->safeName($tenant)],
        ) !== null;
    }

    public function create(Tenant $tenant): void
    {
        $name = $this->safeName($tenant);

        DB::connection('central')->statement(
            "CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
    }

    /** Deletes the database and ALL of the tenant's data. Only for tests and removing a tenant on purpose. */
    public function drop(Tenant $tenant): void
    {
        $name = $this->safeName($tenant);

        DB::connection('central')->statement("DROP DATABASE IF EXISTS `{$name}`");
    }

    /**
     * A database name cannot be sent as a "?" value like normal data; it has to be written into the SQL.
     * So we only accept names that match our pattern, which rules out SQL injection.
     */
    private function safeName(Tenant $tenant): string
    {
        if (! preg_match('/^ems_tenant_[a-z0-9_]{1,40}$/', $tenant->database)) {
            throw new InvalidArgumentException("Invalid tenant database name [{$tenant->database}].");
        }

        return $tenant->database;
    }
}
