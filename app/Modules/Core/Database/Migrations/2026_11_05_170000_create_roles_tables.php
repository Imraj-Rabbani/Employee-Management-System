<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT tables for permissions. [FND-05]
 *
 *   roles             legacy `profiles`: a named set of permissions, e.g. "HR Officer"
 *   role_permissions  legacy `permissions`: one row per permission key a role has, e.g. "payroll.run.approve"
 *   user_roles        which role each login has: ONE role per user
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description')->nullable();
            // Administrator: passes every permission check. Set by the seeder, never from a form.
            $table->boolean('is_admin')->default(false);
            // Data scope (legacy profiles.entity): users with this role only see this org unit's employees.
            // No foreign key yet: org_units arrive in Phase 4, enforcement in Phase 5.
            $table->unsignedBigInteger('org_unit_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 100);   // e.g. "hrm.employee.view"

            $table->unique(['role_id', 'permission']);
            $table->index('permission');
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            // The login's id in the CENTRAL users table. No foreign key: it is in another database.
            $table->unsignedBigInteger('user_id')->unique();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();   // a role still in use cannot be deleted
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
    }
};
