<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT table `employees` (legacy `employees`). [EMP-01]
 *
 * The small "pick one from a list" columns (gender, religion, status ...) are tinyints that hold
 * the int value of an enum in App\Modules\Hrm\Enums (cast on the model, 5.1b).
 *
 * Foreign keys:
 *   - org data from Phase 4 (designation, grade ...)  real FKs now, restrict on delete
 *     (a grade that employees still use cannot be deleted).
 *   - supervisor / dotted-line manager                 self-referencing FK (another employee).
 *   - shift, weekend/holiday/leave/salary/tax profiles  plain indexed ids: those tables come in
 *     Phases 7–11, which add the FKs (same approach as Phase 3).
 *   - user_id                                          central `users.id`: different database, so
 *     it can never be a real FK (Architecture.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->unique();   // login (5.7), central DB

            // Identity
            $table->string('code', 50)->unique();                  // employee ID shown on screens
            $table->string('access_code', 50)->nullable()->index(); // ID on the attendance device
            $table->string('title', 20)->nullable();                // Mr / Ms / Dr
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('name_local', 200)->nullable();          // Bangla name (legacy nameBn)

            // Personal
            $table->unsignedTinyInteger('gender')->nullable();          // Gender enum
            $table->date('birth_date')->nullable();
            $table->unsignedTinyInteger('marital_status')->nullable();  // MaritalStatus enum
            $table->unsignedTinyInteger('blood_group')->nullable();     // BloodGroup enum
            $table->unsignedTinyInteger('religion')->nullable();        // Religion enum
            $table->string('nationality', 50)->nullable();
            $table->string('national_id', 30)->nullable()->unique();
            $table->string('tin', 30)->nullable()->unique();
            $table->string('passport_no', 30)->nullable();

            // Contact + files (paths on the tenant disk, 2.5)
            $table->string('mobile', 30)->nullable();
            $table->string('personal_email')->nullable();
            $table->string('office_email')->nullable()->unique();
            $table->string('photo_path')->nullable();
            $table->string('signature_path')->nullable();

            // Job
            $table->date('join_date');
            $table->date('planned_confirmation_date')->nullable();
            $table->unsignedTinyInteger('confirmation_status')->nullable();   // enum added with confirmations (Phase 13)
            $table->date('confirmation_date')->nullable();
            // CURRENT org unit. The full dated history lives in employee_org_assignments (5.4), which keeps
            // this column in step. Kept here because the list filter (5.2) and the VisibleToCurrentUser
            // scope need it on every employee query, and a plain column is far faster than a history join.
            $table->foreignId('org_unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('employment_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('dotted_line_manager_id')->nullable()->constrained('employees')->nullOnDelete();

            // Attendance / leave / payroll setup. No FKs yet (tables come in Phases 7–11).
            $table->unsignedBigInteger('shift_id')->nullable()->index();
            $table->boolean('has_shifting')->default(false);
            $table->unsignedBigInteger('weekend_profile_id')->nullable()->index();
            $table->unsignedBigInteger('holiday_profile_id')->nullable()->index();
            $table->unsignedBigInteger('leave_profile_id')->nullable()->index();
            $table->unsignedBigInteger('salary_profile_id')->nullable()->index();
            $table->unsignedBigInteger('pay_scale_profile_id')->nullable()->index();
            $table->unsignedBigInteger('tax_profile_id')->nullable()->index();
            $table->unsignedTinyInteger('payment_method')->nullable();   // PaymentMethod enum

            // Status
            $table->unsignedTinyInteger('status')->default(1)->index();  // EmployeeStatus: 1 Active
            $table->date('separation_date')->nullable();

            $table->timestamps();

            $table->index(['first_name', 'last_name']);   // name search on the list (5.2)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
