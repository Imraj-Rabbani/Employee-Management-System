<?php

use App\Modules\Hrm\Models\ManpowerRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A manager asks for people: "2 Sales Officers for Chattogram by 1 January". Goes through an approval
 * workflow; once approved, HR can open a job posting for it (6.3). [REC-01]
 *
 *   type                  'new' or 'replacement' (ManpowerRequestType)
 *   replaces_employee_id  who is being replaced (only for type = replacement)
 *   salary_min / max      the monthly gross the manager has in mind; compared to the grade range in 6.7
 *   hired_count           how many have been hired against it so far (6.8); "filled" when = headcount
 *   approved_at           set by onApproved(): 6.3 lists only approved requests, without reading approvals
 *
 * No status column: "draft / pending / approved / rejected" comes from the approval rows (approvalStatus()).
 * Also registers the approval type "manpower_request" (see Workflow → Setup).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manpower_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('designation_id')->constrained()->restrictOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();   // the seat, if positions are used
            $table->foreignId('grade_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('employment_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->foreignId('replaces_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->unsignedSmallInteger('headcount');
            $table->unsignedSmallInteger('hired_count')->default(0);
            $table->decimal('salary_min', 14, 2)->nullable();
            $table->decimal('salary_max', 14, 2)->nullable();
            $table->date('needed_by')->nullable();
            $table->text('justification')->nullable();
            $table->unsignedBigInteger('requested_by_user_id')->nullable()->index();   // central users.id
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        if (! DB::table('approval_types')->where('key', 'manpower_request')->exists()) {
            DB::table('approval_types')->insert([
                'key' => 'manpower_request',
                'name' => 'Manpower request',
                'model_class' => ManpowerRequest::class,
                'is_chained' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('approval_types')->where('key', 'manpower_request')->delete();
        Schema::dropIfExists('manpower_requests');
    }
};
