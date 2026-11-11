<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT table `attendance_days` (legacy `attendance`): the RESULT of processing one employee's day. [TA-05]
 * Written only by attendance processing (7.4); payroll reads it (absent days, overtime).
 *
 * All durations are whole MINUTES (no floats; payroll converts to hours). Overtime:
 *   ot_pre_minutes    worked before the shift start  (workdays)
 *   ot_post_minutes   worked after the shift end      (workdays)
 *   ot_minutes        the overtime of the day: pre + post on a workday, ALL worked time on a weekend / holiday
 *   The rate depends on day_type (legacy kept one column per type: otRegular, otWeekend, otHoliday, otFestival).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('date');                                        // the WORKING day (an overnight shift's day = its start date)
            $table->foreignId('shift_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('day_type');                     // Enums\DayType
            $table->unsignedTinyInteger('status');                       // Enums\AttendanceStatus

            $table->dateTime('in_at')->nullable();                       // first punch of the working day
            $table->dateTime('out_at')->nullable();                      // last punch (NULL with a single punch)
            $table->unsignedSmallInteger('punch_count')->default(0);

            $table->unsignedSmallInteger('worked_minutes')->default(0);
            $table->unsignedSmallInteger('late_minutes')->default(0);   // after the shift start (grace not subtracted)
            $table->boolean('is_late')->default(false);                  // late beyond the grace time
            $table->boolean('used_grace')->default(false);               // late, but within grace (counts toward the monthly limit)
            $table->unsignedSmallInteger('early_exit_minutes')->default(0);

            $table->unsignedSmallInteger('ot_pre_minutes')->default(0);
            $table->unsignedSmallInteger('ot_post_minutes')->default(0);
            $table->unsignedSmallInteger('ot_minutes')->default(0);
            $table->unsignedSmallInteger('ot_approved_minutes')->nullable();   // from overtime requests (7.7)

            $table->string('note', 255)->nullable();                     // e.g. "Single punch"
            $table->boolean('is_locked')->default(false);                // payroll closed this period: never reprocess
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'date']);                     // one result per employee and day
            $table->index(['date', 'status']);                           // daily reports
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_days');
    }
};
