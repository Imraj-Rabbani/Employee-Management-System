<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columns made in Phases 2–3 before org_units / employment_types existed get their real
 * foreign keys now (see the "No FK yet" comments in those migrations).
 *
 * restrict, not "set null": if deleting an org unit emptied a workflow's scope, that workflow
 * would silently start applying to EVERY unit. Better to refuse the delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->foreign('scope_org_unit_id')->references('id')->on('org_units')->restrictOnDelete();
            $table->foreign('scope_employment_type_id')->references('id')->on('employment_types')->restrictOnDelete();
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->foreign('org_unit_id')->references('id')->on('org_units')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['org_unit_id']);
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->dropForeign(['scope_org_unit_id']);
            $table->dropForeign(['scope_employment_type_id']);
        });
    }
};
