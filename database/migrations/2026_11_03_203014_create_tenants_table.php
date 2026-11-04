<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central table: one row per company (tenant). Replaces the legacy CLIENTS.clients table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();          // short id, e.g. "sss". Used in the DB name and file paths.
            $table->string('name');                        // company name shown in the app
            $table->string('database', 64)->unique();      // e.g. "ems_tenant_sss"

            // Optional: only needed when this tenant's DB lives on another server
            // or uses its own MySQL login. Empty = same as the central DB.
            $table->string('db_host')->nullable();
            $table->unsignedSmallInteger('db_port')->nullable();
            $table->string('db_username')->nullable();
            $table->text('db_password')->nullable();       // stored encrypted (see the Tenant model)

            $table->json('modules')->nullable();           // enabled modules, e.g. ["hrm", "payroll"]
            $table->json('plugins')->nullable();           // per-tenant plugins (Architecture §8)
            $table->boolean('is_active')->default(true);
            $table->date('expires_at')->nullable();        // subscription end, empty = no expiry
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
