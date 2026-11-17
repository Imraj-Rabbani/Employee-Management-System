<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT tables.
 *
 *   entities   legacy `entities`: the items of each entity type, e.g. Department → IT, Admin, Finance;
 *              Branch → Gulshan, Motijheel. FLAT: an entity may point at a parent entity of ANOTHER
 *              type (e.g. Branch "Gulshan" under Division "Dhaka"), but nothing requires it. The parent
 *              is only used to find the next head when an entity has none (approver type "entity head").
 *   positions  legacy `positions`: a seat in the structure ("Accounts Officer, Finance"),
 *              filled by one employee or vacant. Positions form their own tree: who reports to whom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_type_id')->constrained()->restrictOnDelete();
            $table->string('code', 50)->nullable()->unique();
            $table->string('name', 100);
            // Optional, self-referencing: another entity (of another type). restrict: a parent in use cannot be deleted.
            $table->foreignId('parent_id')->nullable()->constrained('entities')->restrictOnDelete();
            // The entity's head (approver type "entity head"). No FK here: the employees table comes later (FK added then).
            $table->unsignedBigInteger('head_employee_id')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['entity_type_id', 'name']);   // no two Departments called "Finance"
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->nullable()->unique();
            $table->string('name', 100);
            $table->foreignId('parent_id')->nullable()->constrained('positions')->restrictOnDelete();   // reports to
            $table->foreignId('entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained()->restrictOnDelete();
            // Current holder. No FK here: the employees table comes later (FK added then).
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
        Schema::dropIfExists('entities');
    }
};
