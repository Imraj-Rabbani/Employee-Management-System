<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT table `leave_accruals` (8.5): one row per employee, leave type and EARNING PERIOD that was credited.
 * [LV-05]
 *
 * The unique key is what makes the nightly AccrueEarnedLeave safe to run again and again: a period that has a
 * row is never credited twice. The Earned ledger row points back here (source), and `basis` explains the number:
 * "30 of 31 days employed in Dec 2026", "19.5 days worked in Nov 2026 ÷ 18".
 * A period that earned 0 days still gets a row (no ledger row): it is done.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_accruals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');                 // LeaveYear the days were credited to
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('days', 7, 2);
            $table->string('basis', 255);
            $table->foreignId('leave_ledger_id')->nullable()->constrained('leave_ledger')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_accruals');
    }
};
