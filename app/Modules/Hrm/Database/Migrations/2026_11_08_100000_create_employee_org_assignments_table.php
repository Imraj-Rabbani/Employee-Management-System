<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which org unit an employee belonged to, and when (legacy `entityitems`). [EMP-04]
 *
 * One row per period. effective_to NULL = the current one. employees.org_unit_id is a copy of the
 * current row's unit (kept in step by ChangeOrgUnit), because every employee query filters on it.
 *
 * Unlike legacy there is no `type` column / one row per level: org units are a tree, so the row for
 * the lowest unit (e.g. Accounts) already implies Finance and Head Office above it.
 *
 * Existing employees get their first row now: from the join date, in their current unit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_org_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('org_unit_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('reason')->nullable();   // e.g. "Transfer", "Restructuring"
            $table->timestamps();

            $table->unique(['employee_id', 'effective_from']);   // one change per employee per day
        });

        DB::table('employee_org_assignments')->insertUsing(
            ['employee_id', 'org_unit_id', 'effective_from', 'created_at', 'updated_at'],
            DB::table('employees')->whereNotNull('org_unit_id')
                ->select('id', 'org_unit_id', 'join_date', DB::raw('NOW()'), DB::raw('NOW()')),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_org_assignments');
    }
};
