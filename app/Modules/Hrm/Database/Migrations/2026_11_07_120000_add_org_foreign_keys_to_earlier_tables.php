<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columns made before entities / employment_types existed get their real
 * foreign keys now (see the "No FK yet" comments in those migrations).
 *
 * restrict, not "set null": if deleting an entity emptied a workflow's scope, that workflow
 * would silently start applying to EVERY entity. Better to refuse the delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->foreign('scope_entity_id')->references('id')->on('entities')->restrictOnDelete();
            $table->foreign('scope_employment_type_id')->references('id')->on('employment_types')->restrictOnDelete();
        });

        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->foreign('approver_entity_type_id')->references('id')->on('entity_types')->restrictOnDelete();
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->foreign('entity_id')->references('id')->on('entities')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['entity_id']);
        });

        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropForeign(['approver_entity_type_id']);
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->dropForeign(['scope_entity_id']);
            $table->dropForeign(['scope_employment_type_id']);
        });
    }
};
