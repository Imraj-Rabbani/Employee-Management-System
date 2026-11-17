<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which entities an employee belongs to, and when (legacy `entityitems`).
 *
 * An employee has ONE entity per entity type at a time: Department = Finance, Branch = Gulshan,
 * Division = Dhaka ... Each type has its own history: one row per period, effective_to NULL = current.
 * entity_type_id is a copy of the entity's type, so "one current row per type" is easy to query.
 *
 * Change these rows only through Hrm\Actions\ChangeEntity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('entity_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('reason')->nullable();   // e.g. "Transfer", "Restructuring"
            $table->timestamps();

            $table->unique(['employee_id', 'entity_type_id', 'effective_from'], 'employee_entities_one_change_per_day');   // one change per type per day
            $table->index(['entity_id', 'effective_to'], 'employee_entities_current');                          // "employees currently in X"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_entities');
    }
};
