<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT tree tables. [ORG-01]
 *
 *   org_units  legacy `entities` (+ `departments`): the company's structure as a tree.
 *              Each unit points at its parent; the top unit has parent_id NULL.
 *   positions  legacy `positions`: a seat in the structure ("Accounts Officer, Finance"),
 *              filled by one employee or vacant. Positions form their own tree: who reports to whom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_units', function (Blueprint $table) {
            $table->id();
            // Self-referencing foreign key: points at another row of THIS table.
            // restrict: a unit that still has sub-units cannot be deleted.
            $table->foreignId('parent_id')->nullable()->constrained('org_units')->restrictOnDelete();
            $table->foreignId('org_unit_type_id')->constrained()->restrictOnDelete();
            $table->string('code', 50)->nullable()->unique();
            $table->string('name', 100);
            // The unit's head (approver type "org head", Phase 5). No FK yet: employees arrive in Phase 5.
            $table->unsignedBigInteger('head_employee_id')->nullable()->index();
            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['parent_id', 'name']);   // no two "Accounts" under the same parent
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->nullable()->unique();
            $table->string('name', 100);
            $table->foreignId('parent_id')->nullable()->constrained('positions')->restrictOnDelete();   // reports to
            $table->foreignId('org_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
            // Current holder. No FK yet: employees arrive in Phase 5.
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->boolean('is_vacant')->default(true);
            $table->date('effective_from')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
        Schema::dropIfExists('org_units');
    }
};
