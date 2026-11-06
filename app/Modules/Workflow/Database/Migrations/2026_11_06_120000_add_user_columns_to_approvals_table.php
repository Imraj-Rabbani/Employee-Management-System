<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Approvers and requesters as LOGINS (central users.id), decided 2026-11-06 for task 3.3.
 * Employees only arrive in Phase 5; the *_employee_id columns stay for then.
 * The inbox ("My Approvals") looks at approver_user_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            // No FKs: users live in the central database.
            $table->unsignedBigInteger('requester_user_id')->nullable()->after('requester_employee_id')->index();
            $table->unsignedBigInteger('approver_user_id')->nullable()->after('approver_employee_id');

            $table->index(['approver_user_id', 'status']);   // "my pending approvals"
        });
    }

    public function down(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            $table->dropIndex(['approver_user_id', 'status']);
            $table->dropIndex(['requester_user_id']);
            $table->dropColumn(['requester_user_id', 'approver_user_id']);
        });
    }
};
