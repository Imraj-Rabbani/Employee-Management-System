<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT lookup tables of the organisation.
 *
 *   entity_types      legacy `entitytypes`: the company's own KINDS of entity, e.g. Department, Branch,
 *                     Division, Location, Area. A flat list the company can add to.
 *   designations      legacy `designations`: job title on paper, e.g. "Senior Officer"
 *   grades            legacy `grades`: pay level, ordered by `sequence`
 *   employment_types  legacy `employmenttype`: Permanent, Contract, ...
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->unsignedInteger('sequence')->default(0);   // order in lists and forms
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
        Schema::dropIfExists('entity_types');
    }
};
