<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columns made in Phases 3–4 before `employees` existed get their real foreign keys now
 * (see the "No FK yet: employees arrive in Phase 5" comments in those migrations).
 *
 *   org_units.head_employee_id, positions.employee_id   null on delete: the unit / seat simply
 *                                                       becomes headless / vacant.
 *   workflow_steps.approver_employee_id                 restrict: a workflow must not silently lose
 *                                                       its approver.
 *   approvals.requester/approver_employee_id            restrict: approval history must keep pointing
 *                                                       at real people.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_units', function (Blueprint $table) {
            $table->foreign('head_employee_id')->references('id')->on('employees')->nullOnDelete();
        });

        Schema::table('positions', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        });

        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->foreign('approver_employee_id')->references('id')->on('employees')->restrictOnDelete();
        });

        Schema::table('approvals', function (Blueprint $table) {
            $table->foreign('requester_employee_id')->references('id')->on('employees')->restrictOnDelete();
            $table->foreign('approver_employee_id')->references('id')->on('employees')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            $table->dropForeign(['requester_employee_id']);
            $table->dropForeign(['approver_employee_id']);
        });

        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropForeign(['approver_employee_id']);
        });

        Schema::table('positions', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
        });

        Schema::table('org_units', function (Blueprint $table) {
            $table->dropForeign(['head_employee_id']);
        });
    }
};
