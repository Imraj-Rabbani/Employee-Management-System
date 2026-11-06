<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT table: the actual approval requests. [WF-03, WF-04]
 *
 *   approvals  legacy `approvals`: one row per document per level (per approver).
 *              "Leave #12 is waiting for Rahim at level 1" = one row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();

            // Polymorphic link to ANY document: approvable_type = model class (or morph alias),
            // approvable_id = its id. Replaces legacy `approvable` + `primaryKey` columns.
            // morphs() creates both columns AND an index on the pair.
            $table->morphs('approvable');

            $table->foreignId('workflow_step_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('level');   // copied from the step, so history survives step edits

            // No FKs yet: employees arrive in Phase 5.
            $table->unsignedBigInteger('requester_employee_id')->nullable()->index();
            $table->unsignedBigInteger('approver_employee_id')->nullable();

            // pending | approved | rejected | review  (ApprovalStatus enum in task 3.2)
            $table->string('status', 20)->default('pending');

            // Who actually answered (may be a representative). Login id in the CENTRAL users table: no FK.
            $table->unsignedBigInteger('responded_by')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->text('comment')->nullable();
            $table->string('attachment_path')->nullable();   // saved via TenantUploads
            $table->timestamps();

            // "My Approvals" inbox: my pending rows.
            $table->index(['approver_employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
