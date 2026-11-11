<?php

use App\Modules\Payroll\Models\AttendanceCorrection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task 7.6: attendance corrections (legacy `attcorrections` / the correction part of `attclearances`). [TA-06]
 * "On 3 December I came at 09:00 and left at 18:00": once approved, these times replace the punches
 * of that day when attendance is processed.
 *
 * No approval_status column: the status comes from the approvals (HasApprovals); approved_at is stamped by
 * onApproved() and is what attendance processing reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');                                  // the attendance day being corrected
            $table->dateTime('requested_in_at')->nullable();       // null = keep the punch
            $table->dateTime('requested_out_at')->nullable();      // may be the next day (overnight shift)
            $table->string('reason', 500);
            $table->unsignedBigInteger('requested_by_user_id')->nullable();   // central users table: no FK across databases
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'date']);
        });

        Schema::table('attendance_days', function (Blueprint $table) {
            $table->boolean('is_corrected')->default(false)->after('note');   // in / out come from an approved correction
        });

        if (! DB::table('approval_types')->where('key', 'attendance_correction')->exists()) {
            DB::table('approval_types')->insert([
                'key' => 'attendance_correction',
                'name' => 'Attendance correction',
                'model_class' => AttendanceCorrection::class,
                'is_chained' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('approval_types')->where('key', 'attendance_correction')->delete();
        Schema::table('attendance_days', fn (Blueprint $table) => $table->dropColumn('is_corrected'));
        Schema::dropIfExists('attendance_corrections');
    }
};
