<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT tables that DESCRIBE how approvals work. [WF-01, WF-02]
 * (The `approvals` rows themselves come in the next migration.)
 *
 *   approval_types  legacy `approvables`: a kind of document that needs approval, e.g. "leave"
 *   workflows       legacy `workflows` header part: who approves a type, for which scope
 *   workflow_steps  legacy `workflows` level rows: level 1, level 2, ... and who the approver is
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();          // used in code: 'leave', 'increment', 'payroll_run'
            $table->string('name', 100);                  // shown on screen: "Leave application"
            $table->string('model_class')->nullable();    // e.g. App\Modules\Hrm\Models\LeaveApplication
            // Chained: level 2 only starts after level 1 approves. Not chained: all levels at once.
            $table->boolean('is_chained')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_type_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);

            // Scope: which documents this workflow is for. NULL = "any".
            // The engine picks the MOST specific workflow that matches (task 3.3).
            // No foreign keys yet: org_units / employment_types arrive in Phase 4.
            $table->unsignedBigInteger('scope_org_unit_id')->nullable()->index();
            $table->unsignedBigInteger('scope_employment_type_id')->nullable()->index();
            $table->decimal('min_amount', 15, 2)->nullable();   // e.g. loans above 50,000 need the CFO

            $table->boolean('send_email')->default(true);
            $table->boolean('allow_review')->default(true);    // approver may "send back" for changes
            $table->boolean('allow_reject')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('level');          // 1, 2, 3 ...

            // employee | supervisor | org_head | role  (an enum class in task 3.2)
            $table->string('approver_type', 20);
            // Filled only when approver_type = employee. No FK yet: employees arrive in Phase 5.
            $table->unsignedBigInteger('approver_employee_id')->nullable()->index();
            // Filled only when approver_type = role.
            $table->foreignId('approver_role_id')->nullable()->constrained('roles')->nullOnDelete();

            $table->boolean('attachment_required')->default(false);
            $table->json('representatives')->nullable();   // delegates: employee ids who may answer instead
            $table->timestamps();

            $table->unique(['workflow_id', 'level']);       // one step per level
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflows');
        Schema::dropIfExists('approval_types');
    }
};
