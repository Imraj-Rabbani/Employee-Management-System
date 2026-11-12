<?php

use App\Modules\Hrm\Models\LeaveApplication;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task 8.4: leave applications (legacy `leaves`). [LV-04]
 *
 *   leave_applications       one request: type, from–to, half day, delegatee, contact, reason, attachment
 *   leave_application_days   the days it COUNTS, one row per date (1 or 0.5). Not simply from–to: excluded
 *                            weekends / holidays are left out, sandwich-rule days are added (LeaveRules, 8.3).
 *                            Used for overlap checks, attendance (LeaveCalendar) and later payroll (LWP days).
 *
 * No approval_status column (status from the approvals, as rosters / overtime), but the decision is stamped,
 * so simple SQL can tell an application's state:
 *   approved_at   set by onApproved()  → the ledger was debited
 *   rejected_at   set by onRejected()
 *   withdrawn_at  set by WithdrawLeave → its ledger rows reversed, pending approvals removed
 *   "active" (holds its days) = not rejected and not withdrawn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->boolean('is_half_day')->default(false);
            $table->decimal('days', 6, 2);                          // counted days (what the ledger takes)
            $table->unsignedSmallInteger('leave_year');             // LeaveYear of the counted days (never two)
            $table->foreignId('delegatee_id')->nullable()->constrained('employees')->nullOnDelete();   // who does my work
            $table->string('contact_during_leave', 100)->nullable();
            $table->string('reason', 1000)->nullable();
            $table->string('attachment_path')->nullable();          // tenant disk (TenantUploads)
            $table->string('attachment_name')->nullable();
            $table->unsignedBigInteger('requested_by_user_id')->nullable();   // central users: no FK across databases
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->unsignedBigInteger('withdrawn_by_user_id')->nullable();
            $table->string('withdraw_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'from_date']);
        });

        Schema::create('leave_application_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('days', 3, 1);                          // 1 or 0.5

            $table->unique(['leave_application_id', 'date']);
            $table->index(['employee_id', 'date']);
        });

        if (! DB::table('approval_types')->where('key', 'leave')->exists()) {
            DB::table('approval_types')->insert([
                'key' => 'leave',
                'name' => 'Leave',
                'model_class' => LeaveApplication::class,
                'is_chained' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('approval_types')->where('key', 'leave')->delete();
        Schema::dropIfExists('leave_application_days');
        Schema::dropIfExists('leave_applications');
    }
};
