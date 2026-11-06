<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT lookup tables of the organisation. [ORG-01]
 *
 *   org_unit_types    legacy `entitytypes`: the company's own levels, e.g. Function → Department → Location
 *   cost_centers      legacy `costcenters`: where salary cost is booked (GL / accounting)
 *   branches          legacy `branches`
 *   designations      legacy `designations`: job title on paper, e.g. "Senior Officer"
 *   grades            legacy `grades`: pay level, ordered by `sequence`
 *   employment_types  legacy `employmenttype`: Permanent, Contract, ...
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_unit_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            // Self-reference: a "Department" sits under a "Function". NULL = may be a top-level unit.
            $table->foreignId('parent_type_id')->nullable()->constrained('org_unit_types')->nullOnDelete();
            $table->unsignedInteger('sequence')->default(0);   // order in lists: top levels first
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->string('function_code', 50)->nullable();
            $table->string('account_code', 50)->nullable();   // GL account for the accounting export (12.4)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->nullable()->unique();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->nullable()->unique();
            $table->string('name', 100)->unique();
            $table->unsignedInteger('sequence')->default(0);   // 1 = highest (or lowest): the company decides
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('sequence');
        });

        Schema::create('employment_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_types');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('designations');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('cost_centers');
        Schema::dropIfExists('org_unit_types');
    }
};
